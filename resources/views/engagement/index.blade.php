@extends('layouts.app')

@section('title', 'Client engagement')

@section('content')
    <h1 class="h3 mb-3">Client engagement</h1>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <h2 class="h5">Customer loyalty and reminder consent</h2>
    <p>Points are managed manually and do not change invoices. Record email consent only after the customer agrees.</p>
    <div class="card p-3 mb-4">
        @forelse ($customers as $customer)
            <div><a href="{{ route('engagement.customers.show', $customer->id) }}">{{ $customer->name }}</a> ({{ $customer->email }})</div>
        @empty
            <p>No customers yet.</p>
        @endforelse
    </div>

    <h2 class="h5">Promotions</h2>
    <p>Published promotions are informational and visible only in the registered customer portal during the selected dates (Malaysia time).</p>
    <form method="POST" action="{{ route('engagement.promotions.store') }}" class="card p-3 mb-3">
        @csrf
        <label>Title <input class="form-control" name="title" maxlength="255" value="{{ old('title') }}" required></label>
        <label>Description <textarea class="form-control" name="description" maxlength="5000" required>{{ old('description') }}</textarea></label>
        <label>Starts <input class="form-control" type="date" name="starts_on" value="{{ old('starts_on') }}" required></label>
        <label>Ends <input class="form-control" type="date" name="ends_on" value="{{ old('ends_on') }}" required></label>
        <label>Status <select class="form-control" name="active"><option value="0">Draft</option><option value="1">Published</option></select></label>
        <div><button class="btn btn-primary">Add promotion</button></div>
    </form>
    @foreach ($promotions as $promotion)
        <form method="POST" action="{{ route('engagement.promotions.update', $promotion->id) }}" class="card p-3 mb-3">
            @csrf @method('PUT')
            <label>Title <input class="form-control" name="title" maxlength="255" value="{{ $promotion->title }}" required></label>
            <label>Description <textarea class="form-control" name="description" maxlength="5000" required>{{ $promotion->description }}</textarea></label>
            <label>Starts <input class="form-control" type="date" name="starts_on" value="{{ $promotion->starts_on->format('Y-m-d') }}" required></label>
            <label>Ends <input class="form-control" type="date" name="ends_on" value="{{ $promotion->ends_on->format('Y-m-d') }}" required></label>
            <label>Status <select class="form-control" name="active"><option value="0" @selected(! $promotion->active)>Draft</option><option value="1" @selected($promotion->active)>Published</option></select></label>
            <div><button class="btn btn-outline-primary">Save promotion</button></div>
        </form>
    @endforeach
    {{ $promotions->links() }}

    <h2 class="h5">Vehicle reminders</h2>
    <p>Dates and emails are managed manually. Close an incorrect reminder and add a replacement. No reminders are sent automatically; each send creates a new email.</p>
    <form method="POST" action="{{ route('engagement.reminders.store') }}" class="card p-3 mb-3">
        @csrf
        <label>Vehicle <select class="form-control" name="vehicle_id" required><option value="">Select a vehicle</option>
            @foreach ($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->plate_number }} — {{ $vehicle->customer->name }}</option>@endforeach
        </select></label>
        <label>Type <select class="form-control" name="type"><option value="service">Service</option><option value="registration">Registration</option><option value="insurance">Insurance</option></select></label>
        <label>Due date <input class="form-control" type="date" name="due_on" required></label>
        <label>Note (optional; included in email) <input class="form-control" name="note" maxlength="500"></label>
        <div><button class="btn btn-primary">Add reminder</button></div>
    </form>
    @forelse ($reminders as $reminder)
        <div class="card p-3 mb-3">
            <strong>{{ $reminder->vehicle->plate_number }} — {{ ucfirst($reminder->type) }} — {{ $reminder->due_on->format('d M Y') }}</strong>
            <div>{{ $reminder->customer->name }}{{ $reminder->completed_at ? ' — Closed' : ' — Open' }}</div>
            @if ($reminder->vehicle->customer_id !== $reminder->customer_id)<div class="text-danger">Vehicle ownership changed; this reminder cannot be sent.</div>@endif
            @if ($reminder->note)<div>{{ $reminder->note }}</div>@endif
            @if (! $reminder->completed_at)
                <div class="d-flex mt-2">
                    <form method="POST" action="{{ route('engagement.reminders.send', $reminder->id) }}" class="mr-2">@csrf <button class="btn btn-sm btn-primary">Send email</button></form>
                    <form method="POST" action="{{ route('engagement.reminders.complete', $reminder->id) }}">@csrf @method('PATCH') <button class="btn btn-sm btn-outline-secondary">Close reminder</button></form>
                </div>
            @endif
            @foreach ($reminder->attempts->sortByDesc('id') as $attempt)
                <div class="small mt-2">Attempt {{ $attempt->id }}: {{ $attempt->status }} to {{ $attempt->recipient_email }} on {{ $attempt->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} (user {{ $attempt->requested_by_user_id }}){{ $attempt->resolved_by_user_id ? ' — resolved by user '.$attempt->resolved_by_user_id : '' }}{{ $attempt->failure_message ? ' — '.$attempt->failure_message : '' }}
                    @if ($attempt->status === 'sending')
                        <form method="POST" action="{{ route('engagement.reminders.resolve', [$reminder->id, $attempt->id]) }}" onsubmit="return confirm('Have you checked the mail provider? The outcome may be unknown and sending again may create a duplicate.')">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-danger">Mark outcome unknown after checking mail provider</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <p>No reminders yet.</p>
    @endforelse
    {{ $reminders->links() }}
@endsection
