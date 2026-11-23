@extends('layouts.app')

@section('title', __(':provider sandbox result', ['provider' => $provider]))

@section('content')
    <h1 class="h3 mb-3">{{ __(':provider sandbox result', ['provider' => $provider]) }}</h1>
    @if ($paid)
        <p class="alert alert-success" role="status">{{ __(':provider confirmed the test payment.', ['provider' => $provider]) }}</p>
    @else
        <p class="alert alert-warning" role="status">{{ __(':provider has not confirmed the test payment.', ['provider' => $provider]) }}</p>
    @endif
    <p>{{ __('No money was charged and no invoice or payment record was changed.') }}</p>
    <a href="{{ route('payment-preferences.edit') }}">{{ __('Back to payment settings') }}</a>
@endsection
