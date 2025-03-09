<?php
namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::with('customer')->paginate(10);
        return view('vehicles.index', compact('vehicles'));
    }

    public function create()
    {
        $customers = Customer::all();
        return view('vehicles.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'plate_number' => 'required|unique:vehicles,plate_number',
            'chassis_number' => 'required|unique:vehicles,chassis_number',
            'engine_number' => 'required|unique:vehicles,engine_number',
            'brand' => 'required',
            'model' => 'required',
        ]);

        Vehicle::create($request->all());

        return redirect()->route('vehicles.index')->with('success', 'Vehicle added successfully.');
    }

    public function edit(Vehicle $vehicle)
    {
        $customers = Customer::all();
        return view('vehicles.edit', compact('vehicle', 'customers'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'plate_number' => "required|unique:vehicles,plate_number,{$vehicle->id}",
            'chassis_number' => "required|unique:vehicles,chassis_number,{$vehicle->id}",
            'engine_number' => "required|unique:vehicles,engine_number,{$vehicle->id}",
            'brand' => 'required',
            'model' => 'required',
        ]);

        $vehicle->update($request->all());

        return redirect()->route('vehicles.index')->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        return redirect()->route('vehicles.index')->with('success', 'Vehicle deleted successfully.');
    }
}
