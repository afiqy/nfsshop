@extends('layouts.app')
@section('title', 'Confirm email change')
@section('content')
    <h1 class="h3">Confirm account email change</h1>
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    <p>Confirm the email change requested for your signed-in account. If you did not request it, do not continue.</p>
    <form method="POST" action="{{ route('profile.email.confirm.submit') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button class="btn btn-primary">Confirm email change</button>
        <a href="{{ route('profile.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
@endsection
