@extends('layouts.app')

@section('title', 'Customer engagement')

@section('content')
    <a href="{{ route('engagement.index') }}">Back to client engagement</a>
    <h1 class="h3 mt-2">{{ $customer->name }}</h1>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    <h2 class="h5">Vehicle reminder email consent</h2>
    <p>Only enable after the customer agrees to vehicle service and renewal reminders at {{ $customer->email }}. Changing the email clears consent.</p>
    <form method="POST" action="{{ route('engagement.consent', $customer->id) }}" class="mb-4">
        @csrf @method('PUT')
        <label>Consent <select name="opt_in"><option value="0" @selected(! $customer->engagement_reminders_opt_in)>No</option><option value="1" @selected($customer->engagement_reminders_opt_in)>Yes</option></select></label>
        <button class="btn btn-sm btn-primary">Save</button>
    </form>
    <h2 class="h5">Loyalty balance: {{ number_format($balance) }} points</h2>
    <p>Awards and redemptions are manual; they do not adjust invoice totals or transfer money.</p>
    <form method="POST" action="{{ route('engagement.points', $customer->id) }}" class="card p-3 mb-3">
        @csrf
        <label>Action <select name="action" class="form-control"><option value="award">Award</option><option value="redeem">Redeem</option></select></label>
        <label>Points <input name="points" type="number" min="1" max="1000000" class="form-control" required></label>
        <label>Reason <input name="reason" maxlength="500" class="form-control" required></label>
        <div><button class="btn btn-primary">Record entry</button></div>
    </form>
    <h2 class="h5">History</h2>
    @forelse ($entries as $entry)
        <div>{{ $entry->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} — {{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }} points — {{ $entry->reason }} (user {{ $entry->recorded_by_user_id }})</div>
    @empty <p>No entries yet.</p> @endforelse
    {{ $entries->links() }}
@endsection
