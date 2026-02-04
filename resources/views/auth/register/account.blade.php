@extends('layouts.guest')

@section('title', 'Register - Account')

@section('content')
<div class="container">
    <h2 class="mb-4">Create Your Account</h2>

    <p class="text-muted">Step 1 of 3 — Tell us about yourself.</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register.account') }}">
        @csrf
        <div class="form-group">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', data_get($state, 'account.name')) }}" required>
        </div>
        <div class="form-group">
            <label for="email">Work email</label>
            <input type="email" id="email" name="email" class="form-control" value="{{ old('email', data_get($state, 'account.email')) }}" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone number</label>
            <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone', data_get($state, 'account.phone')) }}" required>
        </div>
        <div class="form-group">
            <label for="password">Password (min. 8 characters)</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Continue to workshop details</button>
    </form>

    <div class="mt-3">
        <a href="{{ route('login') }}">Already have an account? Log in</a>
    </div>
</div>
@endsection
