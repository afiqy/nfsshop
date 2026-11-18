@extends('layouts.app')

@section('title', $appointment->exists ? 'Reschedule appointment' : 'Book appointment')

@section('content')
    <a href="{{ route('calendar.index') }}">Back to calendar</a>
    <h1 class="h3 mt-2 mb-3">{{ $appointment->exists ? 'Reschedule appointment' : 'Book appointment' }}</h1>
    <p>Enter start and end times in Malaysia time (MYT).</p>

    @if (! $appointment->exists && $vehicles->isEmpty())
        <div class="alert alert-warning">Add a <a href="{{ route('vehicles.create') }}" class="alert-link">vehicle</a> before booking an appointment.</div>
    @else
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $appointment->exists ? route('calendar.update', $appointment) : route('calendar.store') }}" method="POST" class="card shadow-sm p-3">
            @csrf
            @if ($appointment->exists)
                @method('PUT')
                <p>{{ $appointment->service_type ?? $appointment->title }}:
                    {{ $appointment->vehicle?->plate_number ?? 'Vehicle unavailable' }} ({{ $appointment->customer?->name ?? 'Customer unavailable' }})</p>
            @else
                <div class="form-group">
                    <label for="vehicle_id">Vehicle and customer</label>
                    <select id="vehicle_id" name="vehicle_id" class="form-control" required>
                        <option value="">Select a vehicle</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected(in_array(old('vehicle_id'), [$vehicle->id, (string) $vehicle->id], true))>{{ $vehicle->plate_number }} ({{ $vehicle->customer->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="service_type">Service</label>
                    <input type="text" id="service_type" name="service_type" class="form-control" maxlength="255" required
                        value="{{ is_string(old('service_type')) ? old('service_type') : '' }}">
                </div>
            @endif

            @foreach (['start' => 'Starts at', 'end' => 'Ends at'] as $field => $label)
                @php
                    $value = old($field, str_replace(' ', 'T', substr($appointment->{$field} ?? '', 0, 16)));
                @endphp
                <div class="form-group">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input type="datetime-local" id="{{ $field }}" name="{{ $field }}" class="form-control" required
                        value="{{ is_string($value) ? $value : '' }}">
                </div>
            @endforeach

            <div>
                <button type="submit" class="btn btn-primary">{{ $appointment->exists ? 'Save new time' : 'Book appointment' }}</button>
            </div>
        </form>
    @endif
@endsection
