@extends('layouts.app')

@section('title', __('Promotions and loyalty'))

@section('content')
    <a href="{{ route('client.portal') }}">{{ __('Back to my account') }}</a>
    <h1 class="h3 mt-2">{{ __('Promotions, loyalty and reminders') }}</h1>
    <h2 class="h5">{{ __('Current promotions') }}</h2>
    @forelse ($promotions as $promotion)
        <div class="card p-3 mb-2"><strong>{{ $promotion->title }}</strong><div>{{ $promotion->description }}</div><div>{{ __('Until :date', ['date' => $promotion->ends_on->locale(app()->getLocale())->translatedFormat('d M Y')]) }}</div></div>
    @empty <p>{{ __('No current promotions.') }}</p> @endforelse
    {{ $promotions->links() }}
    <h2 class="h5">{{ __('Loyalty balance: :points points', ['points' => number_format($balance)]) }}</h2>
    @forelse ($entries as $entry)
        <div>{{ $entry->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }} — {{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }} {{ __('points') }} — {{ $entry->reason }}</div>
    @empty <p>{{ __('No entries yet.') }}</p> @endforelse
    {{ $entries->links() }}
    <h2 class="h5 mt-4">{{ __('Vehicle reminders') }}</h2>
    @forelse ($reminders as $reminder)
        <div>{{ $reminder->vehicle->plate_number }} — {{ __(ucfirst($reminder->type)) }} — {{ $reminder->due_on->locale(app()->getLocale())->translatedFormat('d M Y') }}{{ $reminder->note ? ' — '.$reminder->note : '' }}</div>
    @empty <p>{{ __('No open reminders.') }}</p> @endforelse
    {{ $reminders->links() }}
    <p class="mt-3">{{ __('Change your reminder email preference on') }} <a href="{{ route('client.portal') }}">{{ __('my account') }}</a>.</p>
@endsection
