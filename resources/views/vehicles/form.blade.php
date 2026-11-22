@extends('layouts.app')

@section('title', $vehicle->exists ? 'Edit vehicle' : 'Add vehicle')

@section('content')
    <a href="{{ route('vehicles.index') }}">Back to vehicles</a>
    <h1 class="h3 mt-2 mb-3">{{ $vehicle->exists ? 'Edit vehicle' : 'Add vehicle' }}</h1>

    @if ($customers->isEmpty())
        <div class="alert alert-warning">Add a <a href="{{ route('customers.create') }}" class="alert-link">customer</a> before registering a vehicle.</div>
    @else
        @php
            $fields = [
                'plate_number' => ['Plate number', 255, true],
                'chassis_number' => ['VIN / chassis number', 255, true],
                'engine_number' => ['Engine number', 255, true],
                'brand' => ['Make', 255, true],
                'model' => ['Model', 255, true],
                'year' => ['Year (optional)', 4, false],
                'color' => ['Color (optional)', 255, false],
                'mileage_km' => ['Mileage in km (optional)', 10, false],
                'fuel_type' => ['Fuel type (optional)', 100, false],
            ];
            $selectedCustomer = old('customer_id', $vehicle->customer_id);
        @endphp

        <form method="POST" action="{{ $vehicle->exists ? route('vehicles.update', $vehicle) : route('vehicles.store') }}" class="card shadow-sm p-3">
            @csrf
            @if ($vehicle->exists)
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="customer_id">Customer</label>
                <p class="small text-muted">Unregistered customers can use a workshop customer record. If ownership changes, each customer can only see service records made for them.</p>
                <select id="customer_id" name="customer_id" class="form-control @error('customer_id') is-invalid @enderror"
                    @error('customer_id') aria-invalid="true" aria-describedby="customer_id-error" @enderror required>
                    <option value="">Select a customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(in_array($selectedCustomer, [$customer->id, (string) $customer->id], true))>{{ $customer->name }} ({{ $customer->email }})</option>
                    @endforeach
                </select>
                @error('customer_id')
                    <div class="invalid-feedback" id="customer_id-error">{{ $message }}</div>
                @enderror
            </div>

            @foreach ($fields as $field => [$label, $maxLength, $required])
                @php
                    $value = old($field, $vehicle->{$field});
                @endphp
                <div class="form-group">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="text" maxlength="{{ $maxLength }}"
                        value="{{ is_scalar($value) ? $value : '' }}" class="form-control @error($field) is-invalid @enderror"
                        @if ($field === 'year') inputmode="numeric" pattern="[0-9]{4}" @endif
                        @if ($field === 'mileage_km') inputmode="numeric" pattern="[0-9]+" @endif
                        @required($required) @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                    @error($field)
                        <div class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach

            <div>
                <button type="submit" class="btn btn-primary">{{ $vehicle->exists ? 'Save changes' : 'Add vehicle' }}</button>
            </div>
        </form>
    @endif
@endsection
