@extends('layouts.app')

@section('title', __('Workshops'))

@section('content')
@php($workshopRoute = $accountArea ? 'account.workshops' : 'super-admin.workshops')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">{{ __('Workshop Management') }}</h1>
        <p class="mb-0 text-muted">{{ __('View, audit, and configure every workshop across the platform.') }}</p>
    </div>
    <div>
        @unless ($accountArea)
            <a href="{{ route('super-admin.owners.index') }}" class="btn btn-outline-primary">{{ __('Manage Owners') }}</a>
        @else
            <a href="{{ route($workshopRoute.'.create') }}" class="btn btn-primary">{{ __('Add Workshop') }}</a>
        @endunless
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <form method="GET" action="{{ route($workshopRoute.'.index') }}" class="form-inline">
            <div class="form-group mr-2">
                <label for="search" class="sr-only">{{ __('Search') }}</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="{{ __('Search workshops or owners') }}" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="form-group mr-2">
                <label for="subscription_status" class="sr-only">{{ __('Subscription Status') }}</label>
                <select name="subscription_status" id="subscription_status" class="form-control">
                    <option value="">{{ __('All subscription statuses') }}</option>
                    @foreach (['active' => 'Active', 'trialing' => 'Trialing', 'past_due' => 'Past Due', 'canceled' => 'Canceled'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['subscription_status'] ?? '') === $value)>{{ __($label) }}</option>
                    @endforeach
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
                        <th>{{ __('Workshop') }}</th>
                        <th>{{ __('Owners') }}</th>
                        <th>{{ __('Contact') }}</th>
                        <th>{{ __('Subscription') }}</th>
                        <th>{{ __('Team') }}</th>
                        <th>{{ __('Clients') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($workshops as $workshop)
                        <tr>
                            <td>
                                <div class="font-weight-bold">{{ $workshop['name'] }}</div>
                                <div class="text-muted small">{{ __('ID:') }} {{ $workshop['id'] }}</div>
                                @if (! empty($workshop['services']))
                                    <div class="text-muted small">{{ __('Services:') }} {{ implode(', ', $workshop['services']) }}</div>
                                @endif
                            </td>
                            <td>
                                @forelse ($workshop['owners'] as $owner)
                                    <div class="small">
                                        {{ $owner->name }}<br>
                                        <a href="mailto:{{ $owner->email }}">{{ $owner->email }}</a>
                                    </div>
                                @empty
                                    <span class="text-muted">{{ __('No linked owner') }}</span>
                                @endforelse
                            </td>
                            <td>
                                <div class="small">{{ $workshop['address'] ?? '—' }}</div>
                                <div class="small">
                                    <a href="mailto:{{ $workshop['owner_email'] }}">{{ $workshop['owner_email'] ?? '—' }}</a>
                                </div>
                                <div class="small">{{ $workshop['phone'] ?? '—' }}</div>
                            </td>
                            <td>
                                <span class="badge badge-{{ ($workshop['subscription']['status'] ?? 'active') === 'active' ? 'success' : 'warning' }}">
                                    {{ __(ucfirst(str_replace('_', ' ', $workshop['subscription']['status'] ?? 'active'))) }}
                                </span>
                                <div class="small text-muted">
                                    {{ __('Plan:') }} {{ $workshop['subscriptionPlan']->name ?? ucfirst($workshop['subscription']['plan'] ?? '-') }}
                                </div>
                                @if ($workshop['subscription']['next_billing_date_formatted'] ?? null)
                                    <div class="small text-muted">{{ __('Next bill:') }} {{ $workshop['subscription']['next_billing_date_formatted'] }}</div>
                                @endif
                            </td>
                            <td>{{ $workshop['metrics']['staff_count'] }}</td>
                            <td>{{ $workshop['metrics']['client_count'] }}</td>
                            <td>{{ optional($workshop['metrics']['created_at'])->locale(app()->getLocale())->translatedFormat('M d, Y') }}</td>
                            <td class="text-right">
                                <a href="{{ route($workshopRoute.'.edit', $workshop['raw']) }}" class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">{{ __('No workshops found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
