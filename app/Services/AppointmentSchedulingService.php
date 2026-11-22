<?php

namespace App\Services;

use App\Models\Calendar;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentSchedulingService
{
    public function validateSlot(Request $request): array
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

    public function lockVehicle(int|string|null $id, string $field = 'vehicle_id'): Vehicle
    {
        $vehicle = Vehicle::query()->lockForUpdate()->find($id);
        if (! $vehicle) {
            throw ValidationException::withMessages([$field => 'The vehicle could not be found in this workshop.']);
        }

        return $vehicle;
    }

    public function ensureAvailable(Vehicle $vehicle, array $slot, ?Calendar $except = null): void
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
}
