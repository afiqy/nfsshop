@extends('layouts.app')

@section('title', 'Job card')

@section('content')
    <a href="{{ route($staffView ? 'my-jobs.index' : 'job-cards.index') }}">{{ $staffView ? 'Back to my jobs' : 'Back to job cards' }}</a>
    <h1 class="h3 mt-2 mb-3">Job #{{ $job->id }}</h1>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('status')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('assigned_mechanic_id')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('note')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    <div class="card shadow-sm p-3">
        <dl>
            <dt>Status</dt>
            <dd>{{ \App\Models\JobCard::LABELS[$job->status] }}</dd>
            @unless ($staffView)
                <dt>Assigned mechanic</dt>
                <dd>{{ $job->assigned_mechanic_id ? ($assigneeName ?? 'Unavailable') : 'Unassigned' }}</dd>
            @endunless
            <dt>Customer</dt>
            <dd>{{ $job->customer?->name ?? 'Unavailable' }}</dd>
            <dt>Vehicle</dt>
            <dd>{{ $job->vehicle?->plate_number ?? 'Unavailable' }}</dd>
            @if ($job->appointment)
                <dt>Appointment</dt>
                <dd>
                    @if ($staffView)
                        {{ substr($job->appointment->start, 0, 16) }} MYT
                    @else
                        <a href="{{ route('calendar.index', ['week' => substr($job->appointment->start, 0, 10)]) }}">{{ substr($job->appointment->start, 0, 16) }} MYT</a>
                    @endif
                    ({{ $job->appointment->status }})
                </dd>
            @endif
            @if ($job->completed_at)
                <dt>Completed at</dt>
                <dd>{{ $job->completed_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT</dd>
            @endif
            <dt>Work requested</dt>
            <dd>{!! nl2br(e($job->description)) !!}</dd>
        </dl>
        @if (! $staffView && $job->nextStatus())
            <form method="POST" action="{{ route('job-cards.assign', $job) }}" class="mb-3">
                @csrf
                @method('PATCH')
                <label for="assigned_mechanic_id">Change assignment</label>
                <select class="form-control mb-2" id="assigned_mechanic_id" name="assigned_mechanic_id">
                    <option value="">Unassigned</option>
                    @foreach ($mechanics as $mechanic)
                        <option value="{{ $mechanic->id }}" @selected($job->assigned_mechanic_id === $mechanic->id)>{{ $mechanic->name }} (#{{ $mechanic->id }})</option>
                    @endforeach
                </select>
                @if ($mechanics->isEmpty())
                    <p class="text-muted">No active mechanics are available in this workshop.</p>
                @endif
                <button type="submit" class="btn btn-outline-primary">Save assignment</button>
            </form>
        @endif
        @if ($nextStatus = $job->nextStatus())
            <form method="POST" action="{{ route($staffView ? 'my-jobs.advance' : 'job-cards.advance', $job) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $nextStatus }}">
                <button type="submit" class="btn btn-primary">{{ $nextStatus === \App\Models\JobCard::COMPLETED ? 'Mark completed' : 'Start work' }}</button>
            </form>
        @endif
    </div>

    <section class="card shadow-sm p-3 mt-3" aria-labelledby="job-updates">
        <h2 class="h5" id="job-updates">Progress and service notes</h2>
        @if ($job->updates->isEmpty())
            <p>No progress history recorded for this job yet.</p>
        @else
            <ol class="list-group list-group-flush mb-3">
                @foreach ($job->updates as $update)
                    <li class="list-group-item">
                        <strong>
                            @if ($update->type === \App\Models\JobCardUpdate::TYPE_OPENED)
                                Job opened
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_STATUS)
                                Status changed to {{ \App\Models\JobCard::LABELS[$update->status] ?? 'Updated' }}
                            @else
                                Service note
                            @endif
                        </strong>
                        <span class="d-block text-muted">
                            {{ $update->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT
                            &middot; {{ $update->actor_name }}
                        </span>
                        @if ($update->type === \App\Models\JobCardUpdate::TYPE_NOTE)
                            <div>{!! nl2br(e($update->note)) !!}</div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        <form method="POST" action="{{ route($staffView ? 'my-jobs.notes.store' : 'job-cards.notes.store', $job) }}">
            @csrf
            <div class="form-group">
                <label for="note">Add a service note</label>
                <textarea class="form-control" id="note" name="note" rows="4" maxlength="4000" required>{{ is_string(old('note')) ? old('note') : '' }}</textarea>
                <small class="form-text text-muted">Notes are permanent and visible to workshop admins and mechanics assigned to this job. Do not include passwords or payment-card details.</small>
            </div>
            <button type="submit" class="btn btn-outline-primary">Add note</button>
        </form>
    </section>
@endsection
