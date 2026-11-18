<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(): View
    {
        return view('vehicles.index', [
            'vehicles' => Vehicle::with('customer')->orderBy('plate_number')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Vehicle());
    }

    public function store(Request $request): RedirectResponse
    {
        Vehicle::create($this->validateVehicle($request));

        return redirect()->route('vehicles.index')->with('success', 'Vehicle added successfully.');
    }

    public function edit(string $vehicle): View
    {
        return $this->form(Vehicle::query()->findOrFail($vehicle));
    }

    public function update(Request $request, string $vehicle): RedirectResponse
    {
        $vehicle = Vehicle::query()->findOrFail($vehicle);
        $vehicle->update($this->validateVehicle($request, $vehicle));

        return redirect()->route('vehicles.index')->with('success', 'Vehicle updated successfully.');
    }

    private function form(Vehicle $vehicle): View
    {
        return view('vehicles.form', [
            'vehicle' => $vehicle,
            'customers' => Customer::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'email']),
        ]);
    }

    private function validateVehicle(Request $request, ?Vehicle $vehicle = null): array
    {
        $rules = [
            'customer_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists(Customer::class, 'id')],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'digits:4'],
            'color' => ['nullable', 'string', 'max:255'],
        ];

        foreach (['plate_number', 'chassis_number', 'engine_number'] as $field) {
            $unique = Rule::unique(Vehicle::class, $field);
            if ($vehicle) {
                $unique->ignore($vehicle);
            }
            $rules[$field] = ['bail', 'required', 'string', 'max:255', $unique];
        }

        return $request->validate($rules);
    }
}
