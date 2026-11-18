@extends('layouts.app')

@section('title', $provider.' sandbox result')

@section('content')
    <h1 class="h3 mb-3">{{ $provider }} sandbox result</h1>
    @if ($paid)
        <p class="alert alert-success" role="status">{{ $provider }} confirmed the test payment.</p>
    @else
        <p class="alert alert-warning" role="status">{{ $provider }} has not confirmed the test payment.</p>
    @endif
    <p>No money was charged and no invoice or payment record was changed.</p>
    <a href="{{ route('payment-preferences.edit') }}">Back to payment settings</a>
@endsection
