@extends('layouts.app')
@section('title', __('Accept staff invitation'))
@section('content')
    <h1 class="h4">{{ __('Join :workshop', ['workshop' => $tenant->workshop_name ?? data_get($tenant->data, 'workshop_name', $tenant->id)]) }}</h1>
    <p>{{ __('Account: :email. Role: :role.', ['email' => $invitation->email, 'role' => __($invitation->role === 'admin' ? 'Workshop admin' : 'Mechanic')]) }}</p>
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    <form method="POST" action="{{ route('staff-invitations.existing.accept', $token) }}">
        @csrf
        <button class="btn btn-primary">{{ __('Accept staff invitation') }}</button>
    </form>
@endsection
