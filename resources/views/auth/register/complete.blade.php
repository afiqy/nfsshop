@extends('layouts.guest')

@section('title', __('Registration Complete'))

@section('content')
<div class="container text-center">
    <h2 class="mb-3">{{ __('You are all set!') }}</h2>
    <p class="lead">{{ __('Your trial has started and we have prepared your workshop dashboard.') }}</p>

    <div class="card mx-auto" style="max-width: 480px;">
        <div class="card-body">
            <p class="mb-1">{{ __('Next steps:') }}</p>
            <ul class="text-left">
                <li>{{ __('Invite your staff and assign roles.') }}</li>
                <li>{{ __('Complete your business profile before issuing invoices.') }}</li>
                <li>{{ __('Explore billing to pick the right package before your trial ends.') }}</li>
            </ul>
            <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('Go to my dashboard') }}</a>
        </div>
    </div>
</div>
@endsection
