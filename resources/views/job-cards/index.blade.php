@extends('layouts.app')

@section('title', 'Job cards')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ $staffView ? 'My jobs' : 'Job cards' }}</h1>
        @unless ($staffView)
            <a href="{{ route('job-cards.create') }}" class="btn btn-primary">Open job card</a>
        @endunless
    </div>
    <nav class="mb-3" aria-label="Filter job cards">
        <a class="btn {{ $overdueOnly ? 'btn-outline-secondary' : 'btn-secondary' }}"
            href="{{ route($staffView ? 'my-jobs.index' : 'job-cards.index') }}"
            @if (! $overdueOnly) aria-current="page" @endif>All jobs</a>
        <a class="btn {{ $overdueOnly ? 'btn-danger' : 'btn-outline-danger' }}"
            href="{{ route($staffView ? 'my-jobs.index' : 'job-cards.index', ['filter' => 'overdue']) }}"
            @if ($overdueOnly) aria-current="page" @endif>Overdue only</a>
    </nav>
    @unless ($staffView)
        @include('exports.controls', [
            'id' => 'jobs-export',
            'csvUrl' => route('exports.index', ['type' => 'jobs', 'format' => 'csv'] + ($overdueOnly ? ['filter' => 'overdue'] : [])),
            'pdfUrl' => route('exports.index', ['type' => 'jobs', 'format' => 'pdf']),
            'filters' => $overdueOnly ? ['filter' => 'overdue'] : [],
        ])
    @endunless
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Job</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Vehicle</th>
                        <th scope="col">Status</th>
                        <th scope="col">Target date</th>
                        @unless ($staffView)
                            <th scope="col">Assigned mechanic</th>
                        @endunless
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td><a href="{{ route($staffView ? 'my-jobs.show' : 'job-cards.show', $job) }}">Job #{{ $job->id }}</a></td>
                            <td>{{ $job->customer?->name ?? 'Unavailable' }}</td>
                            <td>{{ $job->vehicle?->plate_number ?? 'Unavailable' }}</td>
                            <td>{{ \App\Models\JobCard::LABELS[$job->status] }}</td>
                            <td>
                                {{ $job->due_date?->format('d M Y') ?? '—' }}
                                @if ($job->isOverdue($today))
                                    <span class="badge badge-danger">Overdue</span>
                                @endif
                            </td>
                            @unless ($staffView)
                                <td>{{ $job->assigned_mechanic_id ? ($assigneeNames[$job->assigned_mechanic_id] ?? 'Unavailable') : 'Unassigned' }}</td>
                            @endunless
                        </tr>
                    @empty
                        <tr><td colspan="{{ $staffView ? 5 : 6 }}" class="text-center">
                            {{ $overdueOnly ? 'No overdue jobs.' : ($staffView ? 'No jobs assigned to you.' : 'No job cards yet.') }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $jobs->links() }}</div>
@endsection
