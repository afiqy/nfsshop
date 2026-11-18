@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Appointment calendar</h1>
        <a href="{{ route('calendar.create') }}" class="btn btn-primary">Book appointment</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('week')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <h2 class="h5">{{ $weekStart->format('d M Y') }} to {{ $weekStart->addDays(6)->format('d M Y') }}</h2>
    <p>All times shown in Malaysia time (MYT).</p>
    <nav class="mb-3" aria-label="Calendar week">
        <a class="btn btn-outline-secondary" href="{{ route('calendar.index', ['week' => $weekStart->subWeek()->toDateString()]) }}">Previous week</a>
        <a class="btn btn-outline-secondary" href="{{ route('calendar.index') }}">This week</a>
        <a class="btn btn-outline-secondary" href="{{ route('calendar.index', ['week' => $weekStart->addWeek()->toDateString()]) }}">Next week</a>
    </nav>
    <form method="GET" action="{{ route('calendar.index') }}" class="form-inline mb-3">
        <label for="week" class="mr-2">Jump to week of</label>
        <input id="week" name="week" type="date" class="form-control mr-2" value="{{ $weekStart->toDateString() }}" required>
        <button type="submit" class="btn btn-outline-secondary">Go</button>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Start / End</th>
                        <th scope="col">Service</th>
                        <th scope="col">Customer and vehicle</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
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
                            <td>{{ $appointment->service_type ?? $appointment->title }}</td>
                            <td>{{ $appointment->customer?->name ?? 'Customer unavailable' }}<br>{{ $appointment->vehicle?->plate_number ?? 'Vehicle unavailable' }}</td>
                            <td>{{ $appointment->status }}</td>
                            <td>
                                @if ($appointment->status === \App\Models\Calendar::STATUS_SCHEDULED)
                                    @if ($appointment->vehicle)
                                        <a href="{{ route('calendar.edit', $appointment) }}">Reschedule</a>
                                    @endif
                                    <form action="{{ route('calendar.cancel', $appointment) }}" method="POST" class="mt-1"
                                        onsubmit="return confirm('Cancel this appointment?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No appointments this week.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
