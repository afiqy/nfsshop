@extends('layouts.guest')

@section('title', __('Register'))

@section('content')
<div class="container">
    <h2>{{ __('Register Your Workshop') }}</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="form-group">
            <label for="workshop_name">{{ __('Workshop Name') }}</label>
            <input type="text" id="workshop_name" name="workshop_name" class="form-control" value="{{ old('workshop_name') }}" required>
        </div>
        <div class="form-group">
            <label for="name">{{ __('Your Name') }}</label>
            <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required>
        </div>
        <div class="form-group">
            <label for="email">{{ __('Email') }}</label>
            <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required>
        </div>
        <div class="form-group">
            <label for="password">{{ __('Password (min. 8 characters)') }}</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="password_confirmation">{{ __('Confirm Password') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">{{ __('Create Workshop') }}</button>
    </form>

    <div class="mt-3">
        <a href="{{ route('login') }}">{{ __('Already registered? Log in') }}</a>
    </div>
</div>
@endsection
