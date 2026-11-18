<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalendarController extends Controller
{
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $details = $request->validate([
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'service_type' => ['required', 'string', 'max:255'],
        ]);
        $slot = $this->validateSlot($request);

        DB::connection('tenant')->transaction(function () use ($details, $slot) {
            $vehicle = $this->lockVehicle($details['vehicle_id']);
            $this->ensureAvailable($vehicle, $slot);

            Calendar::create($slot + [
                'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'title' => $details['service_type'],
                'service_type' => $details['service_type'],
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
        $slot = $this->validateSlot($request);

        DB::connection('tenant')->transaction(function () use ($booking, $slot) {
            $vehicle = $this->lockVehicle($booking->vehicle_id, 'start');
            $current = Calendar::query()->lockForUpdate()->findOrFail($booking->id);
            $this->ensureScheduled($current);
            $this->ensureAvailable($vehicle, $slot, $current);
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

    private function validateSlot(Request $request): array
    {
        $times = $request->validate([
            'start' => ['bail', 'required', 'date_format:Y-m-d\TH:i'],
            'end' => ['bail', 'required', 'date_format:Y-m-d\TH:i', 'after:start'],
        ]);

        return [
            'start' => str_replace('T', ' ', $times['start']).':00',
            'end' => str_replace('T', ' ', $times['end']).':00',
        ];
    }

    private function lockVehicle(int|string|null $id, string $field = 'vehicle_id'): Vehicle
    {
        // All slot writers lock the vehicle first to serialize bookings for that vehicle.
        $vehicle = Vehicle::query()->lockForUpdate()->find($id);
        if (! $vehicle) {
            throw ValidationException::withMessages([$field => 'The vehicle could not be found in this workshop.']);
        }

        return $vehicle;
    }

    private function ensureAvailable(Vehicle $vehicle, array $slot, ?Calendar $except = null): void
    {
        $overlap = Calendar::query()->where('vehicle_id', $vehicle->id)
            ->where('status', Calendar::STATUS_SCHEDULED)
            ->where('start', '<', $slot['end'])
            ->whereRaw('COALESCE(`end`, `start`) > ?', [$slot['start']])
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start' => 'This vehicle already has an appointment in that time slot.']);
        }
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

