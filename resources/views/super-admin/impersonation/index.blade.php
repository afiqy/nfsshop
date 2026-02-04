@extends('layouts.app')

@section('title', 'Switch to Workshop')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Switch to Workshop</h1>
        <p class="mb-0 text-muted">Select a workshop owner to view the system exactly as they do.</p>
    </div>
    <a href="{{ route('super-admin.owners.index') }}" class="btn btn-outline-primary">Manage Owners</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <form method="GET" action="{{ route('super-admin.impersonation.index') }}" class="form-inline">
            <div class="form-group mr-2">
                <label for="search" class="sr-only">Search workshops</label>
                <input type="text" name="search" id="search" value="{{ $search ?? '' }}" class="form-control" placeholder="Search by workshop, owner or email">
            </div>
            <button type="submit" class="btn btn-outline-primary">Search</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Workshop</th>
                        <th>Owner</th>
                        <th>Email</th>
                        <th>Tenant ID</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($owners as $owner)
                        <tr>
                            <td>{{ $owner->workshop_name ?? optional($owner->tenant)->data['workshop_name'] ?? '—' }}</td>
                            <td>{{ $owner->name }}</td>
                            <td><a href="mailto:{{ $owner->email }}">{{ $owner->email }}</a></td>
                            <td>{{ $owner->tenant_id ?? '—' }}</td>
                            <td>
                                @if ($owner->status === \App\Models\User::STATUS_ACTIVE)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Suspended</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('super-admin.impersonation.start') }}" onsubmit="return confirm('Switch into this workshop?');">
                                    @csrf
                                    <input type="hidden" name="owner_id" value="{{ $owner->id }}">
                                    <button type="submit" class="btn btn-sm btn-primary" {{ $owner->status === \App\Models\User::STATUS_SUSPENDED ? 'disabled' : '' }}>Browse as owner</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No owners matched your search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        {{ $owners->links() }}
    </div>
</div>
@endsection
