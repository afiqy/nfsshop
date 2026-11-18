@extends('layouts.app')

@section('title', 'Job card')

@section('content')
    <a href="{{ route('job-cards.index') }}">Back to job cards</a>
    <h1 class="h3 mt-2 mb-3">Job #{{ $job->id }}</h1>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('status')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    <div class="card shadow-sm p-3">
        <dl>
            <dt>Status</dt>
            <dd>{{ \App\Models\JobCard::LABELS[$job->status] }}</dd>
            <dt>Customer</dt>
            <dd>{{ $job->customer?->name ?? 'Unavailable' }}</dd>
            <dt>Vehicle</dt>
            <dd>{{ $job->vehicle?->plate_number ?? 'Unavailable' }}</dd>
            @if ($job->appointment)
                <dt>Appointment</dt>
                <dd><a href="{{ route('calendar.index', ['week' => substr($job->appointment->start, 0, 10)]) }}">{{ substr($job->appointment->start, 0, 16) }} MYT</a> ({{ $job->appointment->status }})</dd>
            @endif
            @if ($job->completed_at)
                <dt>Completed at</dt>
                <dd>{{ $job->completed_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT</dd>
            @endif
            <dt>Work requested</dt>
            <dd>{!! nl2br(e($job->description)) !!}</dd>
        </dl>
        @if ($nextStatus = $job->nextStatus())
            <form method="POST" action="{{ route('job-cards.advance', $job) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $nextStatus }}">
                <button type="submit" class="btn btn-primary">{{ $nextStatus === \App\Models\JobCard::COMPLETED ? 'Mark completed' : 'Start work' }}</button>
            </form>
        @endif
    </div>
@endsection
