@extends('layouts.app')

@section('title', 'Edit Workshop Owner')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit Workshop Owner</h1>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow">
    <div class="card-body">
        <form action="{{ route('super-admin.owners.update', $owner) }}" method="POST">
            @csrf
            @method('PUT')
            @include('super-admin.owners._form', ['owner' => $owner, 'tenants' => $tenants])
        </form>
    </div>
</div>
@endsection
