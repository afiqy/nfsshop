@extends('layouts.guest')

@section('title', __('Forgot password'))

@section('content')
<div class="card my-5"><div class="card-body">
    <h1 class="h4">{{ __('Forgot password') }}</h1>
    <p>{{ __('Enter your account email to request a password recovery link.') }}</p>
    @if (session('success')) <div class="alert alert-success" role="status">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="form-group"><label for="email">{{ __('Email') }}</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></div>
        <button class="btn btn-primary" type="submit">{{ __('Request recovery link') }}</button>
    </form>
    <p class="mt-3"><a href="{{ route('login') }}">{{ __('Back to login') }}</a></p>
</div></div>
@endsection
