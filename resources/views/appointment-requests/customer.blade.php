@extends('layouts.app')
@section('title', 'Appointment requests')
@section('content')
<a href="{{ route('client.portal') }}">Back to my account</a>
<h1 class="h3 mt-2">Appointment requests</h1>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<p>Requests are not bookings until the workshop confirms them. Times are in Malaysia time (MYT); contact the workshop to change a request or appointment.</p>
<form method="POST" action="{{ route('client.appointment-requests.store') }}" class="card p-3 mb-4">
    @csrf
    <div class="form-group"><label for="vehicle_id">Vehicle</label><select id="vehicle_id" name="vehicle_id" class="form-control" required>
        <option value="">Select your vehicle</option>
        @foreach ($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id') === (string) $vehicle->id)>{{ $vehicle->plate_number }}</option>@endforeach
    </select></div>
    @include('services.selection', ['allowLegacy' => false])
    @foreach (['start' => 'Requested start', 'end' => 'Requested end'] as $field => $label)
        <div class="form-group"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="datetime-local" value="{{ old($field) }}" class="form-control" required></div>
    @endforeach
    <button class="btn btn-primary" @disabled($vehicles->isEmpty())>Request appointment</button>
</form>
<h2 class="h5">My requests</h2>
@forelse ($requests as $booking)
    <div class="card p-3 mb-2">
        <strong>{{ $booking->service_quote['name'] }}</strong> &middot; {{ $booking->vehicle?->plate_number }} &middot;
        {{ $booking->calendar && $booking->calendar->status === \App\Models\Calendar::STATUS_CANCELLED ? 'Cancelled' : ucfirst($booking->status) }}
        <div>{{ collect($booking->service_quote['services'])->pluck('name')->join(', ') }} &middot; Estimate {{ \App\Models\Invoice::formatAmount($booking->service_quote['total_sen']) }}</div>
        <div>{{ substr($booking->calendar?->start ?? $booking->start, 0, 16) }} to {{ substr($booking->calendar?->end ?? $booking->end, 0, 16) }} MYT</div>
        @if ($booking->service_quote['notes'])<div>Notes: {{ $booking->service_quote['notes'] }}</div>@endif
    </div>
@empty <p>No requests yet.</p> @endforelse
{{ $requests->links() }}
@endsection
