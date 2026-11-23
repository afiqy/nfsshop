<?php

namespace App\Http\Controllers;

use App\Models\AppointmentNotification;
use App\Models\AppointmentRequest;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\ServicePackage;
use App\Models\WorkshopService;
use App\Services\AppointmentNotificationService;
use App\Services\AppointmentSchedulingService;
use App\Services\ServiceQuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentRequestController extends Controller
{
    public function __construct(
        private AppointmentSchedulingService $scheduling,
        private ServiceQuoteService $quotes,
        private AppointmentNotificationService $notifications,
    ) {}

    public function customerIndex(Request $request): Response
    {
        $customer = $this->customer($request);

        return response()->view('appointment-requests.customer', [
            'customer' => $customer,
            'requests' => AppointmentRequest::query()->where('customer_id', $customer->id)->with(['vehicle', 'calendar'])->latest('id')->paginate(15),
            'appointments' => Calendar::query()->where('customer_id', $customer->id)
                ->whereHas('vehicle', fn ($query) => $query->where('customer_id', $customer->id))
                ->with(['vehicle', 'jobCard'])->latest('start')->paginate(15, ['*'], 'appointments_page'),
            'vehicles' => $customer->vehicles()->orderBy('plate_number')->get(),
            'services' => WorkshopService::query()->where('is_active', true)->orderBy('name')->get(),
            'packages' => ServicePackage::query()->where('is_active', true)->orderBy('name')->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $data = $request->validate(['vehicle_id' => ['required', 'integer', 'min:1']]);
        $slot = $this->futureSlot($request);
        DB::connection('tenant')->transaction(function () use ($customer, $data, $slot, $request) {
            $quote = $this->quotes->fromRequest($request);
            $vehicle = $this->scheduling->lockVehicle($data['vehicle_id']);
            if ($vehicle->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['vehicle_id' => 'Select one of your vehicles.']);
            }
            $this->assertFuture($slot['start']);
            AppointmentRequest::create($slot + [
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customer->id,
                'service_quote' => $quote,
                'status' => AppointmentRequest::PENDING,
            ]);
            $this->notifications->queue('request_received', $customer, $vehicle, $quote['name'], $slot['start'], $slot['end']);
        }, 3);

        return redirect()->route('client.appointment-requests.index')->with('success', 'Request sent. The workshop must confirm it; emails are queued.');
    }

    public function index(): View
    {
        return view('appointment-requests.admin', [
            'requests' => AppointmentRequest::query()->where('status', AppointmentRequest::PENDING)
                ->with(['customer', 'vehicle'])->orderBy('start')->paginate(15),
            'failedNotifications' => AppointmentNotification::query()->whereIn('status', ['failed', 'sending'])
                ->latest('id')->paginate(20, ['*'], 'notifications_page')->withQueryString(),
        ]);
    }

    public function retryNotification(string $notification): RedirectResponse
    {
        $updated = AppointmentNotification::query()->whereKey($notification)->where('status', 'failed')
            ->update(['status' => 'pending', 'failure_message' => null, 'recipient_email' => null]);
        abort_unless($updated, 409, 'Only a failed email can be retried.');

        return redirect()->route('appointment-requests.index')->with('success', 'Email queued for retry. Check the mail provider first to avoid duplicates.');
    }

    public function resolveNotification(Request $request, string $notification): RedirectResponse
    {
        $updated = AppointmentNotification::query()->whereKey($notification)->where('status', 'sending')
            ->update([
                'status' => 'unknown', 'resolved_by_user_id' => $request->user()->id,
                'resolved_at' => now(), 'failure_message' => 'Manually marked outcome unknown after provider check.',
            ]);
        abort_unless($updated, 409, 'Only an email still marked sending can be resolved.');

        return redirect()->route('appointment-requests.index')->with('success', 'Email marked outcome unknown; contact the recipient if needed.');
    }

    public function confirm(string $appointmentRequest): RedirectResponse
    {
        $booking = AppointmentRequest::query()->findOrFail($appointmentRequest);
        DB::connection('tenant')->transaction(function () use ($booking) {
            $vehicle = $this->scheduling->lockVehicle($booking->vehicle_id);
            $current = AppointmentRequest::query()->lockForUpdate()->findOrFail($booking->id);
            abort_unless($current->status === AppointmentRequest::PENDING, 409, 'This request has already been reviewed.');
            if ($vehicle->customer_id !== $current->customer_id) {
                throw ValidationException::withMessages(['vehicle_id' => 'The vehicle no longer belongs to this customer.']);
            }
            if ($current->start <= CarbonImmutable::now(Calendar::TIMEZONE)->toDateTimeString()) {
                throw ValidationException::withMessages(['start' => 'The requested time has passed. Reject this request and ask the customer to submit another.']);
            }
            $slot = ['start' => $current->start, 'end' => $current->end];
            $this->scheduling->ensureAvailable($vehicle, $slot);
            $appointment = Calendar::create($slot + [
                'customer_id' => $current->customer_id, 'vehicle_id' => $vehicle->id,
                'title' => $current->service_quote['name'], 'service_type' => $current->service_quote['name'],
                'service_quote' => $current->service_quote, 'status' => Calendar::STATUS_SCHEDULED,
            ]);
            $current->update(['status' => AppointmentRequest::CONFIRMED, 'calendar_id' => $appointment->id]);
            $this->notifications->queue('confirmed', Customer::query()->findOrFail($current->customer_id), $vehicle,
                $current->service_quote['name'], $current->start, $current->end, $appointment);
        }, 3);

        return redirect()->route('appointment-requests.index')->with('success', 'Appointment confirmed.');
    }

    public function reject(string $appointmentRequest): RedirectResponse
    {
        $booking = AppointmentRequest::query()->findOrFail($appointmentRequest);
        DB::connection('tenant')->transaction(function () use ($booking) {
            $vehicle = $this->scheduling->lockVehicle($booking->vehicle_id);
            $current = AppointmentRequest::query()->lockForUpdate()->findOrFail($booking->id);
            abort_unless($current->status === AppointmentRequest::PENDING, 409, 'This request has already been reviewed.');
            $current->update(['status' => AppointmentRequest::REJECTED]);
            $this->notifications->queue('request_rejected', Customer::query()->findOrFail($current->customer_id), $vehicle,
                $current->service_quote['name'], $current->start, $current->end);
        }, 3);

        return redirect()->route('appointment-requests.index')->with('success', 'Request rejected.');
    }

    public function customerUpdate(Request $request, string $appointmentRequest): RedirectResponse
    {
        return $this->changeRequest($request, $appointmentRequest, false);
    }

    public function customerCancel(Request $request, string $appointmentRequest): RedirectResponse
    {
        return $this->changeRequest($request, $appointmentRequest, true);
    }

    public function updateAppointment(Request $request, string $appointment): RedirectResponse
    {
        return $this->changeAppointment($request, $appointment, false);
    }

    public function cancelAppointment(Request $request, string $appointment): RedirectResponse
    {
        return $this->changeAppointment($request, $appointment, true);
    }

    private function changeRequest(Request $request, string $id, bool $cancel): RedirectResponse
    {
        $customer = $this->customer($request);
        $booking = AppointmentRequest::query()->where('customer_id', $customer->id)->findOrFail($id);
        $slot = $cancel ? null : $this->futureSlot($request);
        DB::connection('tenant')->transaction(function () use ($customer, $booking, $slot, $cancel) {
            $vehicle = $this->scheduling->lockVehicle($booking->vehicle_id);
            $current = AppointmentRequest::query()->where('customer_id', $customer->id)->lockForUpdate()->findOrFail($booking->id);
            abort_unless($vehicle->customer_id === $customer->id, 404);
            abort_unless($current->status === AppointmentRequest::PENDING
                && $current->start > CarbonImmutable::now(Calendar::TIMEZONE)->toDateTimeString(), 409,
                'Only a pending request that has not started can be changed.');
            if ($cancel) {
                $current->update(['status' => AppointmentRequest::CANCELLED]);
            } else {
                $this->assertFuture($slot['start']);
                if ($current->start === $slot['start'] && $current->end === $slot['end']) {
                    throw ValidationException::withMessages(['start' => 'Choose a different appointment time.']);
                }
                $current->update($slot);
            }
            $this->notifications->queue($cancel ? 'request_cancelled' : 'request_rescheduled', $customer, $vehicle,
                $current->service_quote['name'], $current->start, $current->end);
        }, 3);

        return redirect()->route('client.appointment-requests.index')->with('success', $cancel ? 'Request cancelled. Emails are queued.' : 'Request rescheduled. Emails are queued.');
    }

    private function changeAppointment(Request $request, string $id, bool $cancel): RedirectResponse
    {
        $customer = $this->customer($request);
        $booking = Calendar::query()->where('customer_id', $customer->id)->findOrFail($id);
        $slot = $cancel ? null : $this->futureSlot($request);
        DB::connection('tenant')->transaction(function () use ($customer, $booking, $slot, $cancel) {
            $vehicle = $this->scheduling->lockVehicle($booking->vehicle_id);
            $current = Calendar::query()->where('customer_id', $customer->id)->lockForUpdate()->findOrFail($booking->id);
            abort_unless($vehicle->customer_id === $customer->id, 404);
            abort_unless($current->status === Calendar::STATUS_SCHEDULED
                && $current->start > CarbonImmutable::now(Calendar::TIMEZONE)->toDateTimeString()
                && ! $current->jobCard()->exists(), 409,
                'Only a scheduled appointment that has not started and has no job card can be changed.');
            if ($cancel) {
                $current->update(['status' => Calendar::STATUS_CANCELLED]);
            } else {
                $this->assertFuture($slot['start']);
                if ($current->start === $slot['start'] && $current->end === $slot['end']) {
                    throw ValidationException::withMessages(['start' => 'Choose a different appointment time.']);
                }
                $this->scheduling->ensureAvailable($vehicle, $slot, $current);
                $current->update($slot);
            }
            $this->notifications->queue($cancel ? 'cancelled' : 'rescheduled', $customer, $vehicle,
                $current->service_type ?? $current->title, $current->start, $current->end, $current);
        }, 3);

        return redirect()->route('client.appointment-requests.index')->with('success', $cancel ? 'Appointment cancelled. Emails are queued.' : 'Appointment rescheduled. Emails are queued.');
    }

    private function futureSlot(Request $request): array
    {
        $slot = $this->scheduling->validateSlot($request);
        $this->assertFuture($slot['start']);

        return $slot;
    }

    private function assertFuture(string $start): void
    {
        if ($start <= CarbonImmutable::now(Calendar::TIMEZONE)->toDateTimeString()) {
            throw ValidationException::withMessages(['start' => 'Choose a future appointment time.']);
        }
    }

    private function customer(Request $request): Customer
    {
        return Customer::query()->where('portal_user_id', $request->user()->id)->firstOrFail();
    }
}
