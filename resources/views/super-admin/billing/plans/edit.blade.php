@extends('layouts.app')

@section('title', 'Edit Plan')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Edit Plan: {{ $plan->name }}</h1>
        <p class="mb-0 text-muted">Update pricing, trial period, or feature list.</p>
    </div>
    <a href="{{ route('super-admin.billing.plans.index') }}" class="btn btn-outline-secondary">Back to plans</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

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
        <form method="POST" action="{{ route('super-admin.billing.plans.update', $plan) }}">
            @csrf
            @method('PUT')
            @include('super-admin.billing.plans._form', ['plan' => $plan])
        </form>
    </div>
</div>
@endsection
