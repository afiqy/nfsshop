@extends('layouts.app')

@section('title', 'Open job card')

@section('content')
    <a href="{{ route('job-cards.index') }}">Back to job cards</a>
    <h1 class="h3 mt-2 mb-3">Open job card</h1>

    @if (! $appointment && $vehicles->isEmpty())
        <div class="alert alert-warning">Add a <a href="{{ route('vehicles.create') }}" class="alert-link">vehicle</a> before opening a job card.</div>
    @else
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @include('services.selection', ['allowLegacy' => true])
        @endif
        @php
            $description = old('description', $appointment?->service_type ?? $appointment?->title);
            if (! old('description') && $appointment?->service_quote) {
                $description = \Illuminate\Support\Str::limit($appointment->service_quote['name'].': '.collect($appointment->service_quote['services'])->pluck('name')->join(', ')
                    .($appointment->service_quote['notes'] ? "\n".$appointment->service_quote['notes'] : ''), 4000, '');
            }
        @endphp
        <form action="{{ route('job-cards.store') }}" method="POST" class="card shadow-sm p-3">
            @csrf
            @if ($appointment)
                <input type="hidden" name="calendar_id" value="{{ $appointment->id }}">
                <input type="hidden" name="vehicle_id" value="{{ $appointment->vehicle_id }}">
                <p>Appointment: {{ $appointment->title }} on {{ substr($appointment->start, 0, 16) }} MYT<br>
                    {{ $appointment->vehicle->plate_number }} ({{ $appointment->customer?->name ?? 'Customer unavailable' }})</p>
            @else
                <div class="form-group">
                    <label for="vehicle_id">Vehicle and customer</label>
                    <select id="vehicle_id" name="vehicle_id" class="form-control" required>
                        <option value="">Select a vehicle</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected(in_array(old('vehicle_id'), [$vehicle->id, (string) $vehicle->id], true))>{{ $vehicle->plate_number }} ({{ $vehicle->customer?->name ?? 'Customer unavailable' }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="form-group">
                <label for="description">Work requested</label>
                <textarea id="description" name="description" class="form-control" rows="5" maxlength="4000" required>{{ is_string($description) ? $description : '' }}</textarea>
            </div>
            <div class="form-group">
                <label for="due_date">Target completion date (optional)</label>
                <input id="due_date" name="due_date" type="date" min="{{ $today }}" max="9999-12-31"
                    class="form-control" value="{{ is_string(old('due_date')) ? old('due_date') : '' }}">
                <small class="form-text text-muted">The job is overdue from the day after this date if it is still open.</small>
            </div>
            <div><button type="submit" class="btn btn-primary">Open job card</button></div>
        </form>
    @endif
@endsection
