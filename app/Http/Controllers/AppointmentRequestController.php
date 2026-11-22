<?php

namespace App\Http\Controllers;

use App\Models\AppointmentRequest;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\ServicePackage;
use App\Models\WorkshopService;
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
    public function __construct(private AppointmentSchedulingService $scheduling, private ServiceQuoteService $quotes) {}

    public function customerIndex(Request $request): Response
    {
        $customer = $this->customer($request);

        return response()->view('appointment-requests.customer', [
            'requests' => AppointmentRequest::query()->where('customer_id', $customer->id)->with(['vehicle', 'calendar'])->latest('id')->paginate(15),
            'vehicles' => $customer->vehicles()->orderBy('plate_number')->get(),
            'services' => WorkshopService::query()->where('is_active', true)->orderBy('name')->get(),
            'packages' => ServicePackage::query()->where('is_active', true)->orderBy('name')->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $data = $request->validate(['vehicle_id' => ['required', 'integer', 'min:1']]);
        $slot = $this->scheduling->validateSlot($request);
        if ($slot['start'] <= CarbonImmutable::now(Calendar::TIMEZONE)->toDateTimeString()) {
            throw ValidationException::withMessages(['start' => 'Choose a future appointment time.']);
        }
        DB::connection('tenant')->transaction(function () use ($customer, $data, $slot, $request) {
            $quote = $this->quotes->fromRequest($request);
            $vehicle = $this->scheduling->lockVehicle($data['vehicle_id']);
            if ($vehicle->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['vehicle_id' => 'Select one of your vehicles.']);
            }
            AppointmentRequest::create($slot + [
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customer->id,
                'service_quote' => $quote,
                'status' => AppointmentRequest::PENDING,
            ]);
        }, 3);

        return redirect()->route('client.appointment-requests.index')->with('success', 'Request sent. The workshop must confirm it.');
    }

    public function index(): View
    {
        return view('appointment-requests.admin', [
            'requests' => AppointmentRequest::query()->where('status', AppointmentRequest::PENDING)
                ->with(['customer', 'vehicle'])->orderBy('start')->paginate(15),
        ]);
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
        }, 3);

        return redirect()->route('appointment-requests.index')->with('success', 'Appointment confirmed.');
    }

    public function reject(string $appointmentRequest): RedirectResponse
    {
        $updated = AppointmentRequest::query()->whereKey($appointmentRequest)->where('status', AppointmentRequest::PENDING)
            ->update(['status' => AppointmentRequest::REJECTED]);
        abort_unless($updated, 409, 'This request has already been reviewed or does not exist.');

        return redirect()->route('appointment-requests.index')->with('success', 'Request rejected.');
    }

    private function customer(Request $request): Customer
    {
        return Customer::query()->where('portal_user_id', $request->user()->id)->firstOrFail();
    }
}
