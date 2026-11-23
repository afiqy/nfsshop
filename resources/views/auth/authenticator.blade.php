@extends('layouts.app')
@section('title', __('Authenticator'))
@section('content')
    <a href="{{ route('profile.index') }}">{{ __('Back to profile') }}</a>
    <h1 class="h3 mt-2">{{ __('Authenticator') }}</h1>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    @if ($user->mfa_secret)
        <p>{{ __('Authenticator protection is enabled. Verify before a protected action; verification lasts five minutes.') }}</p>
        <form method="POST" action="{{ route('authenticator.verify') }}" class="card p-3 mb-3">
            @csrf
            <label for="verify-code">{{ __('Authenticator or recovery code') }}</label>
            <input id="verify-code" name="mfa_code" class="form-control mb-2" autocomplete="one-time-code" maxlength="20" required>
            <div><button class="btn btn-primary">{{ __('Verify') }}</button></div>
        </form>
        @unless (session()->has('impersonator_id'))
            <form method="POST" action="{{ route('authenticator.disable') }}" class="card p-3">
                @csrf @method('DELETE')
                <h2 class="h5">{{ __('Disable authenticator') }}</h2>
                <label for="disable-password">{{ __('Current password') }}</label>
                <input id="disable-password" name="password" type="password" class="form-control mb-2" autocomplete="current-password" required>
                <label for="disable-code">{{ __('Authenticator or recovery code') }}</label>
                <input id="disable-code" name="mfa_code" class="form-control mb-2" autocomplete="one-time-code" maxlength="20" required>
                <div><button class="btn btn-outline-danger">{{ __('Disable') }}</button></div>
            </form>
        @endunless
    @elseif ($secret)
        <p>{{ __('Add this secret manually to your authenticator app as a time-based account. Treat it as a password.') }}</p>
        <p><strong>{{ $secret }}</strong></p>
        <form method="POST" action="{{ route('authenticator.confirm') }}" class="card p-3">
            @csrf
            <label for="setup-code">{{ __('Six-digit code') }}</label>
            <input id="setup-code" name="mfa_code" class="form-control mb-2" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
            <div><button class="btn btn-primary">{{ __('Confirm setup') }}</button></div>
        </form>
    @elseif ($canEnroll && ! session()->has('impersonator_id'))
        <p>{{ __('Enable an authenticator app for protected actions.') }}</p>
        <form method="POST" action="{{ route('authenticator.start') }}" class="card p-3">
            @csrf
            <label for="password">{{ __('Current password') }}</label>
            <input id="password" name="password" type="password" class="form-control mb-2" autocomplete="current-password" required>
            <div><button class="btn btn-primary">{{ __('Start setup') }}</button></div>
        </form>
    @else
        <p>{{ __('Authenticator setup is available to Enterprise workshop admins and system admins.') }}</p>
    @endif
@endsection
