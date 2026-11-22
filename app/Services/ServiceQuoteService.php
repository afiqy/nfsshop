<?php

namespace App\Services;

use App\Models\ServicePackage;
use App\Models\WorkshopService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceQuoteService
{
    public function fromRequest(Request $request): array
    {
        $data = $request->validate([
            'selection_type' => ['required', Rule::in(['package', 'custom'])],
            'package_id' => ['nullable', 'required_if:selection_type,package', 'integer', 'min:1'],
            'service_ids' => ['nullable', 'required_if:selection_type,custom', 'array', 'min:1', 'max:25'],
            'service_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['selection_type'] === 'package') {
            $package = ServicePackage::query()->where('is_active', true)->lockForUpdate()->find($data['package_id']);
            if (! $package) {
                throw ValidationException::withMessages(['package_id' => 'Select an active package from this workshop.']);
            }

            return ['type' => 'package', 'name' => $package->name, 'services' => $package->services,
                'total_sen' => $package->price_sen, 'notes' => $data['notes'] ?? null];
        }

        $services = WorkshopService::query()->whereIn('id', $data['service_ids'])->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
        if ($services->count() !== count($data['service_ids'])) {
            throw ValidationException::withMessages(['service_ids' => 'Select active services from this workshop.']);
        }

        return ['type' => 'custom', 'name' => 'Custom service', 'services' => $services->map(fn (WorkshopService $service) => [
            'id' => $service->id, 'name' => $service->name, 'price_sen' => $service->price_sen,
        ])->all(), 'total_sen' => $services->sum('price_sen'), 'notes' => $data['notes'] ?? null];
    }
}
