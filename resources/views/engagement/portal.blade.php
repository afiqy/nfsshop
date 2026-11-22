@extends('layouts.app')

@section('title', 'Promotions and loyalty')

@section('content')
    <a href="{{ route('client.portal') }}">Back to my account</a>
    <h1 class="h3 mt-2">Promotions, loyalty and reminders</h1>
    <h2 class="h5">Current promotions</h2>
    @forelse ($promotions as $promotion)
        <div class="card p-3 mb-2"><strong>{{ $promotion->title }}</strong><div>{{ $promotion->description }}</div><div>Until {{ $promotion->ends_on->format('d M Y') }}</div></div>
    @empty <p>No current promotions.</p> @endforelse
    {{ $promotions->links() }}
    <h2 class="h5">Loyalty balance: {{ number_format($balance) }} points</h2>
    @forelse ($entries as $entry)
        <div>{{ $entry->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }} — {{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }} points — {{ $entry->reason }}</div>
    @empty <p>No entries yet.</p> @endforelse
    {{ $entries->links() }}
    <h2 class="h5 mt-4">Vehicle reminders</h2>
    @forelse ($reminders as $reminder)
        <div>{{ $reminder->vehicle->plate_number }} — {{ ucfirst($reminder->type) }} — {{ $reminder->due_on->format('d M Y') }}{{ $reminder->note ? ' — '.$reminder->note : '' }}</div>
    @empty <p>No open reminders.</p> @endforelse
    {{ $reminders->links() }}
    <p class="mt-3">Change your reminder email preference on <a href="{{ route('client.portal') }}">my account</a>.</p>
@endsection
