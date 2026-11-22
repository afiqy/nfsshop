<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\ServicePackage;
use App\Models\WorkshopPricingSetting;
use App\Models\WorkshopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(): View
    {
        return view('services.index', [
            'services' => WorkshopService::query()->orderBy('name')->get(),
            'packages' => ServicePackage::query()->orderBy('name')->get(),
            'settings' => WorkshopPricingSetting::query()->find(1),
        ]);
    }

    public function serviceForm(?string $service = null): View
    {
        return view('services.service-form', [
            'service' => $service ? WorkshopService::query()->findOrFail($service) : new WorkshopService,
        ]);
    }

    public function saveService(Request $request, ?string $service = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'regex:/\A[0-9]{1,6}(?:\.[0-9]{1,2})?\z/'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $record = $service ? WorkshopService::query()->findOrFail($service) : new WorkshopService;
        $record->fill([
            'name' => $data['name'],
            'price_sen' => Invoice::toSen($data['price']),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return redirect()->route('services.index')->with('success', 'Service saved.');
    }

    public function packageForm(?string $package = null): View
    {
        return view('services.package-form', [
            'package' => $package ? ServicePackage::query()->findOrFail($package) : new ServicePackage,
            'services' => WorkshopService::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function savePackage(Request $request, ?string $package = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_ids' => ['required', 'array', 'min:1', 'max:25'],
            'service_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'price' => ['nullable', 'regex:/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        DB::connection('tenant')->transaction(function () use ($data, $request, $package) {
            $services = WorkshopService::query()->whereIn('id', $data['service_ids'])->where('is_active', true)
                ->orderBy('id')->lockForUpdate()->get();
            if ($services->count() !== count($data['service_ids'])) {
                throw ValidationException::withMessages(['service_ids' => 'Select active services from this workshop.']);
            }
            $snapshot = $services->map(fn (WorkshopService $service) => [
                'id' => $service->id, 'name' => $service->name, 'price_sen' => $service->price_sen,
            ])->all();
            $record = $package ? ServicePackage::query()->lockForUpdate()->findOrFail($package) : new ServicePackage;
            $record->fill([
                'name' => $data['name'],
                'services' => $snapshot,
                'price_sen' => isset($data['price']) ? Invoice::toSen($data['price']) : $services->sum('price_sen'),
                'is_active' => $request->boolean('is_active'),
            ])->save();
        }, 3);

        return redirect()->route('services.index')->with('success', 'Package saved.');
    }

    public function updateTax(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tax_percent' => ['required', 'regex:/\A(?:100(?:\.0{1,2})?|[0-9]{1,2}(?:\.[0-9]{1,2})?)\z/'],
        ]);
        WorkshopPricingSetting::query()->updateOrCreate(['id' => 1], ['tax_rate_basis_points' => Invoice::toSen($data['tax_percent'])]);

        return redirect()->route('services.index')->with('success', 'Default tax rate saved.');
    }
}
