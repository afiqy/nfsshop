@extends('layouts.app')

@section('title', __('Customer engagement'))

@section('content')
    <a href="{{ route('engagement.index') }}">{{ __('Back to client engagement') }}</a>
    <h1 class="h3 mt-2">{{ $customer->name }}</h1>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    <h2 class="h5">{{ __('Vehicle reminder email consent') }}</h2>
    <p>{{ __('Only enable after the customer agrees to vehicle service and renewal reminders at :email. Changing the email clears consent.', ['email' => $customer->email]) }}</p>
    <form method="POST" action="{{ route('engagement.consent', $customer->id) }}" class="mb-4">
        @csrf @method('PUT')
        <label>{{ __('Consent') }} <select name="opt_in"><option value="0" @selected(! $customer->engagement_reminders_opt_in)>{{ __('No') }}</option><option value="1" @selected($customer->engagement_reminders_opt_in)>{{ __('Yes') }}</option></select></label>
        <button class="btn btn-sm btn-primary">{{ __('Save') }}</button>
    </form>
    <h2 class="h5">{{ __('Loyalty balance: :points points', ['points' => number_format($balance)]) }}</h2>
    <p>{{ __('Awards and redemptions are manual; they do not adjust invoice totals or transfer money.') }}</p>
    <form method="POST" action="{{ route('engagement.points', $customer->id) }}" class="card p-3 mb-3">
        @csrf
        <label>{{ __('Action') }} <select name="action" class="form-control"><option value="award">{{ __('Award') }}</option><option value="redeem">{{ __('Redeem') }}</option></select></label>
        <label>{{ __('Points') }} <input name="points" type="number" min="1" max="1000000" class="form-control" required></label>
        <label>{{ __('Reason') }} <input name="reason" maxlength="500" class="form-control" required></label>
        <div><button class="btn btn-primary">{{ __('Record entry') }}</button></div>
    </form>
    <h2 class="h5">{{ __('History') }}</h2>
    @forelse ($entries as $entry)
        <div>{{ $entry->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }} — {{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }} {{ __('points') }} — {{ $entry->reason }} ({{ __('User #:id', ['id' => $entry->recorded_by_user_id]) }})</div>
    @empty <p>{{ __('No entries yet.') }}</p> @endforelse
    {{ $entries->links() }}
@endsection
