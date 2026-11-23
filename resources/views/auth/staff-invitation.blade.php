@extends('layouts.guest')

@section('title', 'Accept staff invitation')

@section('content')
<div class="card my-5"><div class="card-body">
    <h1 class="h4">Join {{ data_get($tenant->data, 'workshop_name', $tenant->id) }}</h1>
    <p>Invited email: {{ $invitation->email }}. Role: {{ $invitation->role === 'admin' ? 'Workshop admin' : 'Mechanic' }}.</p>
    @if ($errors->any())
        <div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('staff-invitations.accept', $token) }}">
        @csrf
        <div class="form-group"><label for="name">Name</label><input id="name" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255"></div>
        <div class="form-group"><label for="password">Password (at least 8 characters, up to 72 bytes)</label><input id="password" type="password" name="password" class="form-control" autocomplete="new-password" required minlength="8"></div>
        <div class="form-group"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required minlength="8"></div>
        <button class="btn btn-primary" type="submit">Accept invitation</button>
    </form>
</div></div>
@endsection
