@extends('layouts.app')
@section('title', 'Authenticator')
@section('content')
    <a href="{{ route('profile.index') }}">Back to profile</a>
    <h1 class="h3 mt-2">Authenticator</h1>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    @if ($user->mfa_secret)
        <p>Authenticator protection is enabled. Verify before a protected action; verification lasts five minutes.</p>
        <form method="POST" action="{{ route('authenticator.verify') }}" class="card p-3 mb-3">
            @csrf
            <label for="verify-code">Authenticator or recovery code</label>
            <input id="verify-code" name="mfa_code" class="form-control mb-2" autocomplete="one-time-code" maxlength="20" required>
            <div><button class="btn btn-primary">Verify</button></div>
        </form>
        @unless (session()->has('impersonator_id'))
            <form method="POST" action="{{ route('authenticator.disable') }}" class="card p-3">
                @csrf @method('DELETE')
                <h2 class="h5">Disable authenticator</h2>
                <label for="disable-password">Current password</label>
                <input id="disable-password" name="password" type="password" class="form-control mb-2" autocomplete="current-password" required>
                <label for="disable-code">Authenticator or recovery code</label>
                <input id="disable-code" name="mfa_code" class="form-control mb-2" autocomplete="one-time-code" maxlength="20" required>
                <div><button class="btn btn-outline-danger">Disable</button></div>
            </form>
        @endunless
    @elseif ($secret)
        <p>Add this secret manually to your authenticator app as a time-based account. Treat it as a password.</p>
        <p><strong>{{ $secret }}</strong></p>
        <form method="POST" action="{{ route('authenticator.confirm') }}" class="card p-3">
            @csrf
            <label for="setup-code">Six-digit code</label>
            <input id="setup-code" name="mfa_code" class="form-control mb-2" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
            <div><button class="btn btn-primary">Confirm setup</button></div>
        </form>
    @elseif ($canEnroll && ! session()->has('impersonator_id'))
        <p>Enable an authenticator app for protected actions.</p>
        <form method="POST" action="{{ route('authenticator.start') }}" class="card p-3">
            @csrf
            <label for="password">Current password</label>
            <input id="password" name="password" type="password" class="form-control mb-2" autocomplete="current-password" required>
            <div><button class="btn btn-primary">Start setup</button></div>
        </form>
    @else
        <p>Authenticator setup is available to Enterprise workshop admins and system admins.</p>
    @endif
@endsection
