@extends('layouts.app')

@section('title', 'Job cards')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Job cards</h1>
        <a href="{{ route('job-cards.create') }}" class="btn btn-primary">Open job card</a>
    </div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Job</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Vehicle</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td><a href="{{ route('job-cards.show', $job) }}">Job #{{ $job->id }}</a></td>
                            <td>{{ $job->customer?->name ?? 'Unavailable' }}</td>
                            <td>{{ $job->vehicle?->plate_number ?? 'Unavailable' }}</td>
                            <td>{{ \App\Models\JobCard::LABELS[$job->status] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No job cards yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $jobs->links() }}</div>
@endsection
