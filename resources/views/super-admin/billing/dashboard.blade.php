@extends('layouts.app')

@section('title', 'My Subscription')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">My Subscription</h1>
        <p class="mb-0 text-muted">Your account plan and workshop branch allowance.</p>
    </div>
    <div class="mt-3 mt-sm-0">
        <a href="https://qysoft-tech.com" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
            Manage at Qysoft Tech
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@if ($subscription)
    @php
        $statusClass = in_array($subscription->status, ['active', 'trialing'], true) ? 'success' : 'warning';
    @endphp

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">{{ $subscription->plan->name }} Plan</h6>
                    <span class="badge badge-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-sm-4 mb-3 mb-sm-0">
                            <div class="text-xs font-weight-bold text-uppercase text-muted">Workshop branches</div>
                            <div class="h4 mb-0 text-gray-800">
                                {{ $workshopCount }} / {{ $allowedWorkshops }}
                            </div>
                        </div>
                        <div class="col-sm-4 mb-3 mb-sm-0">
                            <div class="text-xs font-weight-bold text-uppercase text-muted">Included</div>
                            <div class="h4 mb-0 text-gray-800">{{ $subscription->plan->included_workshops }}</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-xs font-weight-bold text-uppercase text-muted">Branch add-ons</div>
                            <div class="h4 mb-0 text-gray-800">{{ $subscription->additional_workshops }}</div>
                        </div>
                    </div>

                    <progress class="w-100 mb-2" value="{{ $workshopCount }}" max="{{ $allowedWorkshops }}"></progress>
                    <p class="small text-muted mb-0">
                        {{ max(0, $allowedWorkshops - $workshopCount) }} branch slots available.
                        @if ($subscription->plan->max_workshops)
                            This plan has an absolute maximum of {{ $subscription->plan->max_workshops }} workshops.
                        @else
                            More branch slots can be added through Qysoft Tech.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Provider Details</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                            <dt class="col-6">Subscription source</dt>
                            <dd class="col-6 text-right">Qysoft Main (sync pending)</dd>

                        <dt class="col-6">Subscription ID</dt>
                        <dd class="col-6 text-right text-break">{{ $subscription->external_subscription_id ?? 'Local record' }}</dd>

                        <dt class="col-6">Period ends</dt>
                        <dd class="col-6 text-right">{{ optional($subscription->current_period_ends_at)->format('M d, Y') ?? 'Not set' }}</dd>

                        <dt class="col-6">Last checked</dt>
                        <dd class="col-6 text-right">{{ optional($subscription->last_checked_at)->diffForHumans() ?? 'Not checked' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-warning">
        No subscription entitlement has been provisioned for this account. Manage packages through
        <a href="https://qysoft-tech.com" target="_blank" rel="noopener noreferrer">qysoft-tech.com</a>
        once Qysoft Main integration is available.
    </div>
@endif
@endsection
