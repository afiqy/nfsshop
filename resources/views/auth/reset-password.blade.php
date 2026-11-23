@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
<div class="card my-5"><div class="card-body">
    <h1 class="h4">Reset password</h1>
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="form-group"><label for="email">Account email</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email', $email) }}" maxlength="255" autocomplete="email" required></div>
        <div class="form-group"><label for="password">New password (at least 8 characters, up to 72 bytes)</label><input id="password" name="password" type="password" class="form-control" autocomplete="new-password" minlength="8" required></div>
        <div class="form-group"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" minlength="8" required></div>
        <button class="btn btn-primary" type="submit">Reset password</button>
    </form>
    <p class="mt-3"><a href="{{ route('password.request') }}">Request another link</a></p>
</div></div>
@endsection
