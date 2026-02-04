@extends('layouts.app')

@section('title', 'Subscription Plans')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Subscription Plans</h1>
        <p class="mb-0 text-muted">Define pricing tiers available to workshop owners.</p>
    </div>
    <a href="{{ route('super-admin.billing.plans.create') }}" class="btn btn-primary">Add Plan</a>
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
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Name</th>
                        <th>Interval</th>
                        <th>Price</th>
                        <th>Trial</th>
                        <th>Status</th>
                        <th>Features</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}</td>
                            <td>{{ ucfirst($plan->billing_interval) }}</td>
                            <td>{{ number_format($plan->price, 2) }}</td>
                            <td>{{ $plan->trial_days }} days</td>
                            <td>
                                @if ($plan->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if (! empty($plan->features))
                                    <ul class="mb-0 small">
                                        @foreach ($plan->features as $feature)
                                            <li>{{ $feature }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('super-admin.billing.plans.edit', $plan) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form action="{{ route('super-admin.billing.plans.destroy', $plan) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this plan? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No subscription plans defined yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
