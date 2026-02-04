@extends('layouts.app')

@section('title', 'Billing Overview')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Billing & Subscriptions</h1>
        <p class="mb-0 text-muted">Monitor subscription health, overdue balances, and recurring revenue.</p>
    </div>
    <a href="{{ route('super-admin.billing.plans.index') }}" class="btn btn-outline-primary">Manage Plans</a>
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

<div class="row">
    <div class="col-md-3">
        <div class="card shadow mb-4 border-left-primary">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">MRR (Active)</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($metrics['monthly_recurring_revenue'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow mb-4 border-left-danger">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue Amount</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($metrics['overdue_total'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow mb-4 border-left-warning">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Past Due Accounts</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $metrics['past_due_count'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow mb-4 border-left-info">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Trialing</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $metrics['trialing_count'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <form method="GET" action="{{ route('super-admin.billing.dashboard') }}" class="form-inline">
            <div class="form-group mr-2">
                <label for="status" class="sr-only">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach ([
                        \App\Models\WorkshopSubscription::STATUS_ACTIVE => 'Active',
                        \App\Models\WorkshopSubscription::STATUS_TRIALING => 'Trialing',
                        \App\Models\WorkshopSubscription::STATUS_PAST_DUE => 'Past Due',
                        \App\Models\WorkshopSubscription::STATUS_CANCELED => 'Canceled',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2">
                <label for="plan_id" class="sr-only">Plan</label>
                <select name="plan_id" id="plan_id" class="form-control">
                    <option value="">All plans</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((int) ($filters['plan_id'] ?? 0) === $plan->id)>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Workshop</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Next Billing</th>
                        <th>Overdue</th>
                        <th>Last Paid</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptions as $subscription)
                        <tr>
                            <td>
                                <div class="font-weight-bold">{{ $subscription->tenant->data['workshop_name'] ?? $subscription->tenant_id }}</div>
                                <div class="small text-muted">ID: {{ $subscription->tenant_id }}</div>
                            </td>
                            <td>{{ $subscription->plan->name ?? ($subscription->tenant->data['subscription']['plan'] ?? '—') }}</td>
                            <td>
                                @switch($subscription->status)
                                    @case(\App\Models\WorkshopSubscription::STATUS_ACTIVE)
                                        <span class="badge badge-success">Active</span>
                                        @break
                                    @case(\App\Models\WorkshopSubscription::STATUS_TRIALING)
                                        <span class="badge badge-info">Trialing</span>
                                        @break
                                    @case(\App\Models\WorkshopSubscription::STATUS_PAST_DUE)
                                        <span class="badge badge-warning">Past Due</span>
                                        @break
                                    @default
                                        <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</span>
                                @endswitch
                            </td>
                            <td>{{ number_format($subscription->amount ?? 0, 2) }}</td>
                            <td>{{ optional($subscription->next_billing_date)->format('M d, Y') ?? '—' }}</td>
                            <td class="{{ ($subscription->overdue_amount ?? 0) > 0 ? 'text-danger font-weight-bold' : '' }}">
                                {{ number_format($subscription->overdue_amount ?? 0, 2) }}
                            </td>
                            <td>{{ optional($subscription->last_paid_at)->format('M d, Y') ?? '—' }}</td>
                            <td class="text-right">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('super-admin.workshops.edit', $subscription->tenant_id) }}" class="btn btn-sm btn-outline-secondary">View Workshop</a>
                                    <form action="{{ route('super-admin.billing.subscriptions.update', $subscription) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="overdue_amount" value="0">
                                        <input type="hidden" name="last_paid_at" value="{{ now()->toDateString() }}">
                                        <input type="hidden" name="status" value="{{ \App\Models\WorkshopSubscription::STATUS_ACTIVE }}">
                                        <button type="submit" class="btn btn-sm btn-outline-success" @disabled(($subscription->overdue_amount ?? 0) == 0)>Mark Paid</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No subscription records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
