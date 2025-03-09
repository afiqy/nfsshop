<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index()
    {
        return view('calendar.index', [
            'customers' => Customer::all(),
            'vehicles' => Vehicle::all()
        ]);
    }

    public function fetchAppointments()
    {
        return response()->json(Calendar::with(['customer', 'vehicle'])->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'service_type' => 'required|string',
            'start' => 'required|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        Calendar::create($request->all());

        return response()->json(['success' => 'Appointment scheduled successfully.']);
    }
}

