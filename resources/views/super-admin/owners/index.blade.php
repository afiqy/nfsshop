@extends('layouts.app')

@section('title', __('Workshop Owners'))

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">{{ __('Workshop Owners') }}</h1>
        <p class="mb-0 text-muted">{{ __('Manage owner accounts, access, and workshop assignments.') }}</p>
    </div>
    <div class="btn-group">
        @if (session()->has('impersonator_id'))
            <form action="{{ route('impersonation.leave') }}" method="POST" class="mr-2">
                @csrf
                <button type="submit" class="btn btn-warning">{{ __('Exit Impersonation') }}</button>
            </form>
        @endif
        <a href="{{ route('super-admin.owners.create') }}" class="btn btn-primary">{{ __('Add Owner') }}</a>
    </div>
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

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <form method="GET" action="{{ route('super-admin.owners.index') }}" class="form-inline">
            <div class="form-group mr-2">
                <label for="search" class="sr-only">{{ __('Search') }}</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="{{ __('Search owners') }}" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="form-group mr-2">
                <label for="status" class="sr-only">{{ __('Status') }}</label>
                <select name="status" id="status" class="form-control">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="{{ \App\Models\User::STATUS_ACTIVE }}" @selected(($filters['status'] ?? '') === \App\Models\User::STATUS_ACTIVE)>{{ __('Active') }}</option>
                    <option value="{{ \App\Models\User::STATUS_SUSPENDED }}" @selected(($filters['status'] ?? '') === \App\Models\User::STATUS_SUSPENDED)>{{ __('Suspended') }}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-outline-primary">{{ __('Filter') }}</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Workshop') }}</th>
                        <th>{{ __('Tenant ID') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($owners as $owner)
                        <tr>
                            <td>{{ $owner->name }}</td>
                            <td><a href="mailto:{{ $owner->email }}">{{ $owner->email }}</a></td>
                            <td>{{ $owner->workshop_name ?? optional($owner->tenant)->data['workshop_name'] ?? '—' }}</td>
                            <td>{{ $owner->tenant_id ?? '—' }}</td>
                            <td>
                                @if ($owner->status === \App\Models\User::STATUS_ACTIVE)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-danger">{{ __('Suspended') }}</span>
                                @endif
                            </td>
                            <td>{{ optional($owner->created_at)->locale(app()->getLocale())->translatedFormat('M d, Y') }}</td>
                            <td class="text-right">
                                <div class="btn-group" role="group" aria-label="{{ __('Owner actions') }}">
                                    <a href="{{ route('super-admin.owners.edit', $owner) }}" class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</a>

                                    @if ($owner->isSuspended())
                                        <form action="{{ route('super-admin.owners.activate', $owner) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Activate') }}</button>
                                        </form>
                                    @else
                                        <form action="{{ route('super-admin.owners.suspend', $owner) }}" method="POST" class="d-inline" onsubmit="return confirm(@json(__('Suspend this owner?')));">
                                            @csrf
                                            <input type="hidden" name="reason" value="Administrative action">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('Suspend') }}</button>
                                        </form>
                                    @endif

                                    <form action="{{ route('super-admin.owners.reset-password', $owner) }}" method="POST" class="d-inline" onsubmit="return confirm(@json(__('Generate a temporary password for this owner?')));">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-info">{{ __('Reset Password') }}</button>
                                    </form>

                                    @if (! session()->has('impersonator_id'))
                                        <form action="{{ route('super-admin.owners.impersonate', $owner) }}" method="POST" class="d-inline" onsubmit="return confirm(@json(__('Impersonate this owner? You will be logged in as them.')));">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Impersonate') }}</button>
                                        </form>
                                    @endif

                                    <form action="{{ route('super-admin.owners.destroy', $owner) }}" method="POST" class="d-inline" onsubmit="return confirm(@json(__('Delete this owner account? This cannot be undone.')));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ __('No workshop owners found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        {{ $owners->links() }}
    </div>
</div>

@if ($tenantsWithoutOwner->isNotEmpty())
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-warning">{{ __('Tenants Without Linked Owner') }}</h6>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                @foreach ($tenantsWithoutOwner as $tenant)
                    <li>
                        <strong>{{ $tenant->id }}</strong>
                        <span class="text-muted">— {{ $tenant->data['workshop_name'] ?? __('Unknown workshop') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
@endsection
