@extends('layouts.app')

@section('title', 'Create Plan')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Create Subscription Plan</h1>
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
        <form method="POST" action="{{ route('super-admin.billing.plans.store') }}">
            @csrf
            @include('super-admin.billing.plans._form')
        </form>
    </div>
</div>
@endsection
