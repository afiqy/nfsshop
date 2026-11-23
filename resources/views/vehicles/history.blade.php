@extends('layouts.app')

@section('title', __('Vehicle service history'))

@section('content')
    <a href="{{ route('vehicles.index') }}">{{ __('Back to vehicles') }}</a>
    <h1 class="h3 mt-2">{{ __('Service history: :plate', ['plate' => $vehicle->plate_number]) }}</h1>
    <p>{{ $vehicle->brand }} {{ $vehicle->model }} &middot; {{ __('Current customer:') }} {{ $vehicle->customer->name }}</p>
    <p>
        {{ __('Year:') }} {{ $vehicle->year ?? __('Not provided') }} &middot; {{ __('Colour:') }} {{ $vehicle->color ?? __('Not provided') }}<br>
        {{ __('Mileage:') }} {{ $vehicle->mileage_km !== null ? number_format($vehicle->mileage_km).' km' : __('Not provided') }}
        &middot; {{ __('Fuel type:') }} {{ $vehicle->fuel_type ?? __('Not provided') }}
    </p>
    <p>{{ __('All dates and times are shown in Malaysia time (MYT).') }}</p>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="appointments">
        <h2 class="h5" id="appointments">{{ __('Appointments') }}</h2>
        <ul class="list-group list-group-flush">
            @forelse ($appointments as $appointment)
                <li class="list-group-item">
                    @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)
                        <a href="{{ route('calendar.index', ['week' => substr($appointment->start, 0, 10)]) }}">{{ $appointment->service_type ?? $appointment->title }}</a>
                    @else
                        {{ $appointment->service_type ?? $appointment->title }}
                    @endif
                    <span class="d-block text-muted">{{ substr($appointment->start, 0, 16) }} &middot; {{ __($appointment->status) }}</span>
                </li>
            @empty
                <li class="list-group-item">{{ __('No appointments for this vehicle.') }}</li>
            @endforelse
        </ul>
        {{ $appointments->fragment('appointments')->links() }}
    </section>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="job-cards">
        <h2 class="h5" id="job-cards">{{ __('Job cards') }}</h2>
        <ul class="list-group list-group-flush">
            @forelse ($jobs as $job)
                <li class="list-group-item">
                    @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)
                        <a href="{{ route('job-cards.show', $job) }}">{{ __('Job #:id: :description', ['id' => $job->id, 'description' => \Illuminate\Support\Str::limit($job->description, 100)]) }}</a>
                    @else
                        {{ __('Job #:id: :description', ['id' => $job->id, 'description' => \Illuminate\Support\Str::limit($job->description, 100)]) }}
                    @endif
                    <span class="d-block text-muted">{{ $job->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }} &middot; {{ __(\App\Models\JobCard::LABELS[$job->status]) }}</span>
                    @foreach ($job->updates as $update)
                        <span class="d-block ml-3">
                            {{ $update->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }} MYT
                            &middot; {{ $update->actor_name }}
                            @if ($update->type === \App\Models\JobCardUpdate::TYPE_NOTE)
                                &middot; {!! nl2br(e($update->note)) !!}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_STATUS)
                                &middot; {{ __('Status:') }} {{ __(\App\Models\JobCard::LABELS[$update->status] ?? 'Updated') }}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_OPENED)
                                &middot; {{ __('Job opened') }}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_VEHICLE_UPDATE)
                                &middot; {{ __('Vehicle update') }}
                                @if ($update->mileage_km !== null)
                                    &middot; {{ __('Mileage:') }} {{ number_format($update->mileage_km) }} km
                                @endif
                                @if ($update->vehicle_condition)
                                    &middot; {{ __('Condition:') }} {!! nl2br(e($update->vehicle_condition)) !!}
                                @endif
                                @if ($update->note)
                                    &middot; {!! nl2br(e($update->note)) !!}
                                @endif
                            @endif
                        </span>
                    @endforeach
                </li>
            @empty
                <li class="list-group-item">{{ __('No job cards for this vehicle.') }}</li>
            @endforelse
        </ul>
        {{ $jobs->fragment('job-cards')->links() }}
    </section>

    <section class="card shadow-sm p-3 mb-3" aria-labelledby="invoices">
        <h2 class="h5" id="invoices">{{ __('Invoices') }}</h2>
        <ul class="list-group list-group-flush">
            @forelse ($invoices as $invoice)
                <li class="list-group-item">
                    @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)
                        <a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number() }}</a>
                    @else
                        {{ $invoice->number() }}
                    @endif
                    <span class="d-block text-muted">{{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }} &middot; {{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}{{ $invoice->isVoided() ? ' · '.__('Voided') : '' }}</span>
                </li>
            @empty
                <li class="list-group-item">{{ __('No invoices for this vehicle.') }}</li>
            @endforelse
        </ul>
        {{ $invoices->fragment('invoices')->links() }}
    </section>
@endsection
