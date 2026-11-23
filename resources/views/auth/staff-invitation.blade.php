@extends('layouts.guest')

@section('title', __('Accept staff invitation'))

@section('content')
<div class="card my-5"><div class="card-body">
    <h1 class="h4">{{ __('Join :workshop', ['workshop' => $tenant->workshop_name ?? data_get($tenant->data, 'workshop_name', $tenant->id)]) }}</h1>
    <p>{{ __('Invited email: :email. Role: :role.', ['email' => $invitation->email, 'role' => __($invitation->role === 'admin' ? 'Workshop admin' : 'Mechanic')]) }}</p>
    @if ($errors->any())
        <div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('staff-invitations.accept', $token) }}">
        @csrf
        <div class="form-group"><label for="name">{{ __('Name') }}</label><input id="name" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255"></div>
        <div class="form-group"><label for="password">{{ __('Password (at least 8 characters, up to 72 bytes)') }}</label><input id="password" type="password" name="password" class="form-control" autocomplete="new-password" required minlength="8"></div>
        <div class="form-group"><label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required minlength="8"></div>
        <button class="btn btn-primary" type="submit">{{ __('Accept staff invitation') }}</button>
    </form>
</div></div>
@endsection
