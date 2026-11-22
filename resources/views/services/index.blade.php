@extends('layouts.app')
@section('title', 'Services and pricing')
@section('content')
<h1 class="h3">Services and pricing</h1>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<section class="card p-3 mb-3">
    <h2 class="h5">Default invoice tax rate</h2>
    <p>Set your own rate after consulting your accountant. Invoices are not MyInvois-validated.</p>
    <form action="{{ route('services.tax') }}" method="POST" class="form-inline">
        @csrf @method('PUT')
        <label for="tax_percent" class="mr-2">Rate (%)</label>
        <input id="tax_percent" name="tax_percent" type="number" min="0" max="100" step="0.01" class="form-control mr-2" required value="{{ old('tax_percent', number_format(($settings?->tax_rate_basis_points ?? 0) / 100, 2, '.', '')) }}">
        <button class="btn btn-primary">Save tax rate</button>
    </form>
</section>
<section class="card p-3 mb-3">
    <div class="d-flex justify-content-between"><h2 class="h5">Services</h2><a href="{{ route('services.create') }}">Add service</a></div>
    @forelse ($services as $service)
        <p><a href="{{ route('services.edit', $service->id) }}">{{ $service->name }}</a> &middot; {{ \App\Models\Invoice::formatAmount($service->price_sen) }} &middot; {{ $service->is_active ? 'Active' : 'Inactive' }}</p>
    @empty <p>No services yet.</p> @endforelse
</section>
<section class="card p-3">
    <div class="d-flex justify-content-between"><h2 class="h5">Packages</h2><a href="{{ route('service-packages.create') }}">Add package</a></div>
    @forelse ($packages as $package)
        <p><a href="{{ route('service-packages.edit', $package->id) }}">{{ $package->name }}</a> &middot; {{ \App\Models\Invoice::formatAmount($package->price_sen) }} &middot; {{ $package->is_active ? 'Active' : 'Inactive' }}<br>
            {{ collect($package->services)->pluck('name')->join(', ') }}</p>
    @empty <p>No packages yet.</p> @endforelse
</section>
@endsection
