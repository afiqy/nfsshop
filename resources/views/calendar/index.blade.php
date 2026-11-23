@extends('layouts.app')

@section('title', __('Calendar'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Appointment calendar') }}</h1>
        <div><a href="{{ route('appointment-requests.index') }}" class="btn btn-outline-primary">{{ __('Appointment requests') }}</a>
        <a href="{{ route('calendar.create') }}" class="btn btn-primary">{{ __('Book appointment') }}</a></div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('week')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <h2 class="h5">{{ $weekStart->locale(app()->getLocale())->translatedFormat('d M Y') }} {{ __('to') }} {{ $weekStart->addDays(6)->locale(app()->getLocale())->translatedFormat('d M Y') }}</h2>
    <p>{{ __('All times shown in Malaysia time (MYT).') }}</p>
    <nav class="mb-3" aria-label="{{ __('Calendar week') }}">
        <a class="btn btn-outline-secondary" href="{{ route('calendar.index', ['week' => $weekStart->subWeek()->toDateString()]) }}">{{ __('Previous week') }}</a>
        <a class="btn btn-outline-secondary" href="{{ route('calendar.index') }}">{{ __('This week') }}</a>
        <a class="btn btn-outline-secondary" href="{{ route('calendar.index', ['week' => $weekStart->addWeek()->toDateString()]) }}">{{ __('Next week') }}</a>
    </nav>
    <form method="GET" action="{{ route('calendar.index') }}" class="form-inline mb-3">
        <label for="week" class="mr-2">{{ __('Jump to week of') }}</label>
        <input id="week" name="week" type="date" class="form-control mr-2" value="{{ $weekStart->toDateString() }}" required>
        <button type="submit" class="btn btn-outline-secondary">{{ __('Go') }}</button>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Start / End') }}</th>
                        <th scope="col">{{ __('Service') }}</th>
                        <th scope="col">{{ __('Customer and vehicle') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                        <tr>
                            <td>
                                {{ substr($appointment->start, 0, 16) }}
                                @if ($appointment->end)
                                    <br>{{ substr($appointment->end, 0, 16) }}
                                @endif
                            </td>
                            <td>{{ $appointment->service_type ?? $appointment->title }}
                                @if ($appointment->service_quote)
                                    <br>{{ collect($appointment->service_quote['services'])->pluck('name')->join(', ') }}
                                    <br>{{ __('Estimate: :amount', ['amount' => \App\Models\Invoice::formatAmount($appointment->service_quote['total_sen'])]) }}
                                    @if ($appointment->service_quote['notes'])<br>{{ __('Notes: :notes', ['notes' => $appointment->service_quote['notes']]) }}@endif
                                @endif
                            </td>
                            <td>{{ $appointment->customer?->name ?? __('Customer unavailable') }}<br>{{ $appointment->vehicle?->plate_number ?? __('Vehicle unavailable') }}</td>
                            <td>{{ __($appointment->status) }}</td>
                            <td>
                                @if ($appointment->jobCard)
                                    <a class="d-block" href="{{ route('job-cards.show', $appointment->jobCard) }}">{{ __('View job card') }}</a>
                                @elseif ($appointment->status === \App\Models\Calendar::STATUS_SCHEDULED
                                    && $appointment->vehicle && $appointment->vehicle->customer_id === $appointment->customer_id)
                                    <a class="d-block" href="{{ route('job-cards.create', ['appointment' => $appointment->id]) }}">{{ __('Open job card') }}</a>
                                @endif
                                @if ($appointment->status === \App\Models\Calendar::STATUS_SCHEDULED)
                                    @if ($appointment->vehicle)
                                        <a href="{{ route('calendar.edit', $appointment) }}">{{ __('Reschedule') }}</a>
                                    @endif
                                    <form action="{{ route('calendar.cancel', $appointment) }}" method="POST" class="mt-1"
                                        onsubmit="return confirm('{{ __('Cancel this appointment?') }}');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">{{ __('No appointments this week.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
