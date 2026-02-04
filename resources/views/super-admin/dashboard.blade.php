@extends('layouts.app')

@section('title', 'Super Admin Dashboard')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Super Admin Dashboard</h1>
        <p class="mb-0 text-muted">High-level overview across all workshops.</p>
    </div>
    <div class="btn-group">
        <a href="{{ route('super-admin.impersonation.index') }}" class="btn btn-outline-secondary">Switch to Workshop</a>
        <a href="{{ route('super-admin.billing.dashboard') }}" class="btn btn-outline-info">Billing Overview</a>
        <a href="{{ route('super-admin.workshops.index') }}" class="btn btn-outline-primary">Manage Workshops</a>
        <a href="{{ route('super-admin.owners.index') }}" class="btn btn-primary">Manage Owners</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-primary">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Owners</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $metrics['total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-success">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Owners</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $metrics['active'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-warning">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Suspended Owners</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $metrics['suspended'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-info">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Workshops</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $workshopMetrics['total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-success">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Subscriptions</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $workshopMetrics['active'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-warning">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Past Due Accounts</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $workshopMetrics['past_due'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-primary">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">MRR (Active)</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($billingMetrics['mrr'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-danger">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue Balance</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($billingMetrics['overdue'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow mb-4 border-left-warning">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Past Due Accounts</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $billingMetrics['past_due_accounts'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Recent Owners</h6>
        <a href="{{ route('super-admin.owners.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th scope="col">Workshop</th>
                        <th scope="col">Owner</th>
                        <th scope="col">Email</th>
                        <th scope="col">Tenant ID</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentOwners as $owner)
                        <tr>
                            <td>{{ $owner->workshop_name ?? optional($owner->tenant)->data['workshop_name'] ?? '—' }}</td>
                            <td>{{ $owner->name }}</td>
                            <td><a href="mailto:{{ $owner->email }}">{{ $owner->email }}</a></td>
                            <td>{{ $owner->tenant_id ?? '—' }}</td>
                            <td>
                                @if ($owner->status === \App\Models\User::STATUS_ACTIVE)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Suspended</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No owner records available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($tenantsWithoutOwner->isNotEmpty())
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-warning">Tenants Without Linked Owner</h6>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                @foreach ($tenantsWithoutOwner as $tenant)
                    <li>
                        <strong>{{ $tenant->id }}</strong>
                        <span class="text-muted">— {{ $tenant->data['workshop_name'] ?? 'Unknown workshop' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
@endsection
