@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<div class="container-fluid">
    <header class="mb-4">
        <h1 class="h3 text-gray-800">{{ $workshopName ? __('Workshop dashboard') : ($staffDashboard ? __('My dashboard') : __('Dashboard')) }}</h1>
        @if ($workshopName)
            <p class="mb-0">{{ $workshopName }} &middot; {{ __('Plan: :plan', ['plan' => $planName ?? __('Not assigned')]) }}</p>
        @endif
    </header>

    @if ($isWorkshopAdmin && ! $subscriptionUsable)
        <div class="alert alert-warning">
            {{ __('Your subscription is inactive or expired. Workshop operations are locked until you renew or reactivate your subscription.') }}
            <a href="{{ route('workshop.subscription') }}" class="alert-link">{{ __('View subscription') }}</a>
        </div>
    @elseif ($isWorkshopAdmin && ! $hasWorkshop)
        <div class="alert alert-warning">{{ __('No workshop is linked to your account.') }}</div>
    @elseif ($staffDashboard)
        <div class="row">
            <div class="col-md-6 mb-4">
                <section class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">{{ __('Jobs assigned to you today') }}</h2>
                        <p class="h4">{{ $todayJobCount }}</p>
                    </div>
                </section>
            </div>
            <div class="col-md-6 mb-4">
                <section class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">{{ __('Pending assigned jobs') }}</h2>
                        <p class="h4">{{ $assignedJobCount }}</p>
                        <a href="{{ route('my-jobs.index') }}">{{ __('View all my jobs') }}</a>
                        <a class="d-block mt-2" href="{{ route('my-jobs.index', ['filter' => 'overdue']) }}">
                            {{ __('Overdue jobs: :count', ['count' => $overdueJobCount]) }}
                        </a>
                    </div>
                </section>
            </div>
        </div>
        <section class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">{{ __("Today's assigned jobs") }}</h2>
                <p>{{ $today->format('D, j M Y') }} ({{ __('Malaysia time') }})</p>
                @if ($todayJobs->isEmpty())
                    <p>{{ __('No assigned jobs scheduled for today.') }}</p>
                @else
                    <ul class="list-group">
                        @foreach ($todayJobs as $job)
                            <li class="list-group-item">
                                {{ \Carbon\CarbonImmutable::parse($job->appointment->start, $today->timezone)->format('H:i') }}
                                &middot;
                                <a href="{{ route('my-jobs.show', $job) }}">{{ __('Job #:id', ['id' => $job->id]) }}: {{ $job->appointment->service_type ?? $job->appointment->title }}</a>
                                &middot; {{ $job->customer?->name ?? __('Customer unavailable') }}
                                &middot; {{ $job->vehicle?->plate_number ?? __('Vehicle unavailable') }}
                                &middot; {{ \App\Models\JobCard::LABELS[$job->status] }}
                                <span class="d-block">{{ $job->description }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($todayJobCount > $todayJobs->count())
                        <p class="mt-2">{{ __('Showing the first :shown of :total assigned jobs today.', ['shown' => $todayJobs->count(), 'total' => $todayJobCount]) }}</p>
                    @endif
                @endif
            </div>
        </section>
    @elseif ($hasWorkshop)
        <nav class="d-flex flex-wrap mb-3" aria-label="{{ __('Workshop quick actions') }}">
            <a class="btn btn-primary mr-2 mb-2" href="{{ route('invoices.create') }}">{{ __('Create invoice') }}</a>
            <a class="btn btn-outline-primary mr-2 mb-2" href="{{ route('job-cards.create') }}">{{ __('Create job card') }}</a>
            <a class="btn btn-outline-primary mr-2 mb-2" href="{{ route('calendar.create') }}">{{ __('Book appointment') }}</a>
            <a class="btn btn-outline-primary mb-2" href="{{ route('vehicles.create') }}">{{ __('Add vehicle') }}</a>
        </nav>

        <div class="row">
            <div class="col-md-4 mb-4">
                <section class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">{{ __('Appointments today') }}</h2>
                        <p class="h4 mb-0">{{ $appointmentCount }}</p>
                    </div>
                </section>
            </div>
            <div class="col-md-4 mb-4">
                <section class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">{{ __('Open or in-progress jobs') }}</h2>
                        <p class="h4">{{ $openJobCount }}</p>
                        <a href="{{ route('job-cards.index') }}">{{ __('View job cards') }}</a>
                        <a class="d-block mt-2" href="{{ route('job-cards.index', ['filter' => 'overdue']) }}">
                            {{ __('Overdue jobs: :count', ['count' => $overdueJobCount]) }}
                        </a>
                    </div>
                </section>
            </div>
            <div class="col-md-4 mb-4">
                <section class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">{{ __('Outstanding invoice balance') }}</h2>
                        <p class="h4">{{ \App\Models\Invoice::formatAmount($outstandingSen) }}</p>
                        <a href="{{ route('invoices.index') }}">{{ __('View invoices') }}</a>
                        <span class="d-block">{{ $outstandingCount }} {{ __(\Illuminate\Support\Str::plural('invoice', $outstandingCount)) }} {{ __('with a balance') }}</span>
                        <a class="d-block mt-2" href="{{ route('invoices.index', ['status' => 'overdue']) }}">
                            {{ __('Overdue: :amount (:count :invoices)', ['amount' => \App\Models\Invoice::formatAmount($overdueSen), 'count' => $overdueCount, 'invoices' => __(Illuminate\Support\Str::plural('invoice', $overdueCount))]) }}
                        </a>
                    </div>
                </section>
            </div>
        </div>

        @if (isset($lowStockCount) && $lowStockCount > 0)
            <section class="alert alert-warning mb-4" aria-labelledby="low-stock-heading">
                <h2 class="h5" id="low-stock-heading">{{ __('Low inventory stock (:count)', ['count' => $lowStockCount]) }}</h2>
                <ul class="mb-2">
                    @foreach ($lowStockItems as $item)
                        <li>
                            <a href="{{ route('inventory.show', $item) }}">{{ $item->sku }} &middot; {{ $item->name }}</a>
                            — {{ __(':quantity on hand (threshold: :threshold)', ['quantity' => $item->quantity, 'threshold' => $item->low_stock_threshold]) }}
                        </li>
                    @endforeach
                </ul>
                @if ($lowStockCount > $lowStockItems->count())
                    <p class="mb-2">{{ __('Showing :shown of :count low-stock items.', ['shown' => $lowStockItems->count(), 'count' => $lowStockCount]) }}</p>
                @endif
                <a class="alert-link" href="{{ route('inventory.index') }}">{{ __('Review inventory') }}</a>
            </section>
        @elseif (isset($lowStockCount))
            <p class="text-muted mb-4">{{ __('No low-stock inventory items.') }}</p>
        @endif

        <section class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">{{ __("Today's schedule") }}</h2>
                <p>{{ $today->format('D, j M Y') }} ({{ __('Malaysia time') }})</p>
                @if ($appointments->isEmpty())
                    <p>{{ __('No appointments scheduled today.') }}</p>
                @else
                    <ul class="list-group mb-3">
                        @foreach ($appointments as $appointment)
                            <li class="list-group-item">
                                {{ \Carbon\CarbonImmutable::parse($appointment->start, $today->timezone)->format('j M, H:i') }}
                                &middot;
                                <a href="{{ route('calendar.edit', $appointment) }}">{{ $appointment->service_type ?? $appointment->title }}</a>
                                @if ($appointment->vehicle)
                                    &middot; {{ $appointment->vehicle->plate_number }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($appointmentCount > $appointments->count())
                        <p>{{ __('Showing the first :shown of :total appointments.', ['shown' => $appointments->count(), 'total' => $appointmentCount]) }}</p>
                    @endif
                @endif
                <a href="{{ route('calendar.index', ['week' => $today->toDateString()]) }}">{{ __('View calendar') }}</a>
            </div>
        </section>
    @else
        <p>{{ __('Welcome, :name.', ['name' => auth()->user()->name]) }}</p>
    @endif
</div>
@endsection
