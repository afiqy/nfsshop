@extends('layouts.guest')

@section('title', __('Subscription Required'))

@section('content')
<div class="container">
    <div class="alert alert-warning">
        <h2 class="h4">{{ __('Subscription Required') }}</h2>
        <p class="mb-0">{{ __('Your workshop subscription is inactive or expired. Please contact your workshop admin to restore access.') }}</p>
    </div>

    @if ($ownerEmail)
        <p>{{ __('Workshop admin:') }} <a href="mailto:{{ $ownerEmail }}">{{ $ownerEmail }}</a></p>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-secondary">{{ __('Log out') }}</button>
    </form>
</div>
@endsection
