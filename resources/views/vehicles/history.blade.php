@extends('layouts.app')

@section('title', 'Vehicle service history')

@section('content')
    <a href="{{ route('vehicles.index') }}">Back to vehicles</a>
    <h1 class="h3 mt-2">Service history: {{ $vehicle->plate_number }}</h1>
    <p>{{ $vehicle->brand }} {{ $vehicle->model }} &middot; Current customer: {{ $vehicle->customer->name }}</p>
    <p>
        Year: {{ $vehicle->year ?? 'Not provided' }} &middot; Colour: {{ $vehicle->color ?? 'Not provided' }}<br>
        Mileage: {{ $vehicle->mileage_km !== null ? number_format($vehicle->mileage_km).' km' : 'Not provided' }}
        &middot; Fuel type: {{ $vehicle->fuel_type ?? 'Not provided' }}
    </p>
    <p>All dates and times are shown in Malaysia time (MYT).</p>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="appointments">
        <h2 class="h5" id="appointments">Appointments</h2>
        <ul class="list-group list-group-flush">
            @forelse ($appointments as $appointment)
                <li class="list-group-item">
                    <a href="{{ route('calendar.index', ['week' => substr($appointment->start, 0, 10)]) }}">{{ $appointment->service_type ?? $appointment->title }}</a>
                    <span class="d-block text-muted">{{ substr($appointment->start, 0, 16) }} &middot; {{ $appointment->status }}</span>
                </li>
            @empty
                <li class="list-group-item">No appointments for this vehicle.</li>
            @endforelse
        </ul>
        {{ $appointments->fragment('appointments')->links() }}
    </section>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="job-cards">
        <h2 class="h5" id="job-cards">Job cards</h2>
        <ul class="list-group list-group-flush">
            @forelse ($jobs as $job)
                <li class="list-group-item">
                    <a href="{{ route('job-cards.show', $job) }}">Job #{{ $job->id }}: {{ \Illuminate\Support\Str::limit($job->description, 100) }}</a>
                    <span class="d-block text-muted">{{ $job->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }} &middot; {{ \App\Models\JobCard::LABELS[$job->status] }}</span>
                    @foreach ($job->updates as $update)
                        <span class="d-block ml-3">
                            {{ $update->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT
                            &middot; {{ $update->actor_name }}
                            @if ($update->type === \App\Models\JobCardUpdate::TYPE_NOTE)
                                &middot; {!! nl2br(e($update->note)) !!}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_STATUS)
                                &middot; Status: {{ \App\Models\JobCard::LABELS[$update->status] ?? 'Updated' }}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_OPENED)
                                &middot; Job opened
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_VEHICLE_UPDATE)
                                &middot; Vehicle update
                                @if ($update->mileage_km !== null)
                                    &middot; Mileage: {{ number_format($update->mileage_km) }} km
                                @endif
                                @if ($update->vehicle_condition)
                                    &middot; Condition: {!! nl2br(e($update->vehicle_condition)) !!}
                                @endif
                                @if ($update->note)
                                    &middot; {!! nl2br(e($update->note)) !!}
                                @endif
                            @endif
                        </span>
                    @endforeach
                </li>
            @empty
                <li class="list-group-item">No job cards for this vehicle.</li>
            @endforelse
        </ul>
        {{ $jobs->fragment('job-cards')->links() }}
    </section>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="invoices">
        <h2 class="h5" id="invoices">Invoices</h2>
        <ul class="list-group list-group-flush">
            @forelse ($invoices as $invoice)
                <li class="list-group-item">
                    <a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number() }}</a>
                    <span class="d-block text-muted">{{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }} &middot; {{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}{{ $invoice->isVoided() ? ' · Voided' : '' }}</span>
                </li>
            @empty
                <li class="list-group-item">No invoices for this vehicle.</li>
            @endforelse
        </ul>
        {{ $invoices->fragment('invoices')->links() }}
    </section>
@endsection
