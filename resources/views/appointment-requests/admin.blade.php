@extends('layouts.app')
@section('title', 'Appointment requests')
@section('content')
@if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)<a href="{{ route('calendar.index') }}">Back to calendar</a>@endif
<h1 class="h3 mt-2">Pending appointment requests</h1>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<p>Review the requested time and workshop capacity before confirming. Only same-vehicle time conflicts are checked automatically.</p>
@if ($failedNotifications->isNotEmpty())
    <section class="alert alert-warning">
        <h2 class="h5">Appointment emails needing attention</h2>
        <p>Check the mail provider before retrying: transport failures can have an uncertain delivery outcome. Emails stuck in sending require investigation and are not retried automatically.</p>
        @foreach ($failedNotifications as $notification)
            <div class="mb-2">
                {{ ucfirst($notification->recipient_type) }} &middot; {{ str_replace('_', ' ', $notification->event) }} &middot; {{ $notification->vehicle_plate }} &middot; {{ $notification->status }}
                @if ($notification->failure_message) &middot; {{ $notification->failure_message }} @endif
                @if ($notification->status === 'failed')
                    <form method="POST" action="{{ route('appointment-notifications.retry', $notification->id) }}" class="d-inline">
                        @csrf <button class="btn btn-sm btn-outline-dark">Queue retry</button>
                    </form>
                @elseif ($notification->status === 'sending')
                    <form method="POST" action="{{ route('appointment-notifications.resolve', $notification->id) }}" class="d-inline">
                        @csrf @method('PATCH') <button class="btn btn-sm btn-outline-dark">Mark outcome unknown after provider check</button>
                    </form>
                @endif
            </div>
        @endforeach
        {{ $failedNotifications->links() }}
    </section>
@endif
@forelse ($requests as $booking)
    <div class="card p-3 mb-2">
        <strong>{{ $booking->customer?->name }} &middot; {{ $booking->vehicle?->plate_number }}</strong>
        <div>{{ substr($booking->start, 0, 16) }} to {{ substr($booking->end, 0, 16) }} MYT</div>
        <div>{{ $booking->service_quote['name'] }}: {{ collect($booking->service_quote['services'])->pluck('name')->join(', ') }}</div>
        <div>Estimate: {{ \App\Models\Invoice::formatAmount($booking->service_quote['total_sen']) }}</div>
        @if ($booking->service_quote['notes'])<div>Notes: {{ $booking->service_quote['notes'] }}</div>@endif
        <div class="mt-2">
            <form method="POST" class="d-inline" action="{{ route('appointment-requests.confirm', $booking->id) }}">@csrf @method('PATCH') <button class="btn btn-primary">Confirm</button></form>
            <form method="POST" class="d-inline" action="{{ route('appointment-requests.reject', $booking->id) }}">@csrf @method('PATCH') <button class="btn btn-outline-danger">Reject</button></form>
        </div>
    </div>
@empty <p>No pending requests.</p> @endforelse
{{ $requests->links() }}
@endsection
