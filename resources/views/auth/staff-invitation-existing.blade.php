@extends('layouts.app')
@section('title', 'Accept staff invitation')
@section('content')
    <h1 class="h4">Join {{ $tenant->workshop_name ?? data_get($tenant->data, 'workshop_name', $tenant->id) }}</h1>
    <p>Account: {{ $invitation->email }}. Role: {{ $invitation->role === 'admin' ? 'Workshop admin' : 'Mechanic' }}.</p>
    @if ($errors->any()) <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
    <form method="POST" action="{{ route('staff-invitations.existing.accept', $token) }}">
        @csrf
        <button class="btn btn-primary">Accept invitation</button>
    </form>
@endsection
