@extends('layouts.app')

@section('title', 'Vehicle service history')

@section('content')
    <a href="{{ route('client.portal') }}">Back to my account</a>
    <h1 class="h3 mt-2">Service history: {{ $vehicle->plate_number }}</h1>
    <p>{{ $vehicle->brand }} {{ $vehicle->model }}{{ $vehicle->year ? ' · '.$vehicle->year : '' }}{{ $vehicle->color ? ' · '.$vehicle->color : '' }}</p>
    <p>
        Mileage: {{ $vehicle->mileage_km !== null ? number_format($vehicle->mileage_km).' km' : 'Not provided' }}
        &middot; Fuel type: {{ $vehicle->fuel_type ?? 'Not provided' }}
    </p>
    <p>Only service records associated with your customer record are shown.</p>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="appointments">
        <h2 class="h5" id="appointments">Appointments</h2>
        <ul class="list-group list-group-flush">
            @forelse ($appointments as $appointment)
                <li class="list-group-item">
                    {{ $appointment->service_type ?? $appointment->title }}
                    <span class="d-block text-muted">{{ substr($appointment->start, 0, 16) }} MYT · {{ $appointment->status }}</span>
                </li>
            @empty
                <li class="list-group-item">No appointments for this vehicle.</li>
            @endforelse
        </ul>
        {{ $appointments->fragment('appointments')->links() }}
    </section>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="job-cards">
        <h2 class="h5" id="job-cards">Service records</h2>
        <ul class="list-group list-group-flush">
            @forelse ($jobs as $job)
                <li class="list-group-item">
                    {{ \Illuminate\Support\Str::limit($job->description, 100) }}
                    <span class="d-block text-muted">{{ $job->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }} · {{ \App\Models\JobCard::LABELS[$job->status] }}</span>
                </li>
            @empty
                <li class="list-group-item">No service records for this vehicle.</li>
            @endforelse
        </ul>
        {{ $jobs->fragment('job-cards')->links() }}
    </section>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="invoices">
        <h2 class="h5" id="invoices">Invoices</h2>
        <ul class="list-group list-group-flush">
            @forelse ($invoices as $invoice)
                <li class="list-group-item">
                    {{ $invoice->number() }}
                    <span class="d-block text-muted">{{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }} · {{ $invoice->isVoided() ? 'Voided' : \App\Models\Invoice::formatAmount($invoice->total_sen) }}</span>
                    <a class="d-block" href="{{ route('client.invoices.show', $invoice) }}">View invoice and payment options</a>
                </li>
            @empty
                <li class="list-group-item">No invoices for this vehicle.</li>
            @endforelse
        </ul>
        {{ $invoices->fragment('invoices')->links() }}
    </section>
@endsection
