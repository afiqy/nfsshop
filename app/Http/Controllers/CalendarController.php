<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Vehicle;
use App\Models\ServicePackage;
use App\Models\WorkshopService;
use App\Services\AppointmentSchedulingService;
use App\Services\ServiceQuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(private AppointmentSchedulingService $scheduling, private ServiceQuoteService $quotes) {}

    public function index(Request $request): View
    {
        $validated = $request->validate(['week' => ['nullable', 'date_format:Y-m-d']]);
        $date = isset($validated['week'])
            ? CarbonImmutable::parse($validated['week'], Calendar::TIMEZONE)
            : CarbonImmutable::now(Calendar::TIMEZONE);
        $weekStart = $date->startOfWeek(CarbonImmutable::MONDAY);
        $weekEnd = $weekStart->addWeek();
        $appointments = Calendar::with(['customer', 'vehicle', 'jobCard'])
            ->where('start', '<', $weekEnd->toDateTimeString())
            ->where(fn ($query) => $query
                ->where('start', '>=', $weekStart->toDateTimeString())
                ->orWhere('end', '>', $weekStart->toDateTimeString()))
            ->orderBy('start')->orderBy('id')->get();

        return view('calendar.index', [
            'weekStart' => $weekStart,
            'appointments' => $appointments,
        ]);
    }

    public function create(): View
    {
        return view('calendar.form', [
            'appointment' => new Calendar(),
            'vehicles' => Vehicle::with('customer')->orderBy('plate_number')->get(),
            'services' => WorkshopService::query()->where('is_active', true)->orderBy('name')->get(),
            'packages' => ServicePackage::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $details = $request->validate([
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'service_type' => ['nullable', 'required_without:selection_type', 'string', 'max:255'],
        ]);
        $slot = $this->scheduling->validateSlot($request);

        DB::connection('tenant')->transaction(function () use ($details, $slot, $request) {
            $quote = $request->filled('selection_type') ? $this->quotes->fromRequest($request) : null;
            $vehicle = $this->scheduling->lockVehicle($details['vehicle_id']);
            $this->scheduling->ensureAvailable($vehicle, $slot);

            Calendar::create($slot + [
                'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'title' => $quote['name'] ?? $details['service_type'],
                'service_type' => $quote['name'] ?? $details['service_type'],
                'service_quote' => $quote,
                'status' => Calendar::STATUS_SCHEDULED,
            ]);
        }, 3);

        return $this->returnToWeek($slot['start'], 'Appointment booked.');
    }

    public function edit(string $appointment): View
    {
        $booking = Calendar::with(['customer', 'vehicle'])->findOrFail($appointment);
        $this->ensureScheduled($booking);

        return view('calendar.form', ['appointment' => $booking]);
    }

    public function update(Request $request, string $appointment): RedirectResponse
    {
        $booking = Calendar::findOrFail($appointment);
        $slot = $this->scheduling->validateSlot($request);

        DB::connection('tenant')->transaction(function () use ($booking, $slot) {
            $vehicle = $this->scheduling->lockVehicle($booking->vehicle_id, 'start');
            $current = Calendar::query()->lockForUpdate()->findOrFail($booking->id);
            $this->ensureScheduled($current);
            $this->scheduling->ensureAvailable($vehicle, $slot, $current);
            $current->update($slot);
        }, 3);

        return $this->returnToWeek($slot['start'], 'Appointment rescheduled.');
    }

    public function cancel(string $appointment): RedirectResponse
    {
        $booking = DB::connection('tenant')->transaction(function () use ($appointment) {
            $booking = Calendar::query()->lockForUpdate()->findOrFail($appointment);
            $this->ensureScheduled($booking);
            $booking->update(['status' => Calendar::STATUS_CANCELLED]);

            return $booking;
        }, 3);

        return $this->returnToWeek($booking->start, 'Appointment cancelled.');
    }

    private function ensureScheduled(Calendar $booking): void
    {
        abort_unless($booking->status === Calendar::STATUS_SCHEDULED, 409, 'This appointment is no longer scheduled.');
    }

    private function returnToWeek(string $start, string $message): RedirectResponse
    {
        return redirect()->route('calendar.index', ['week' => substr($start, 0, 10)])->with('success', $message);
    }
}

