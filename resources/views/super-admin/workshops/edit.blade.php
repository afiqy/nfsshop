@extends('layouts.app')

@section('title', 'Edit Workshop')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">{{ $workshop['name'] }}</h1>
        <p class="mb-0 text-muted">Update contact details, subscription settings, and service offerings.</p>
    </div>
    <a href="{{ route('super-admin.workshops.index') }}" class="btn btn-outline-secondary">Back to workshops</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('super-admin.workshops.update', $workshop['raw']) }}">
                    @csrf
                    @method('PUT')
                    @include('super-admin.workshops._form', ['workshop' => $workshop, 'plans' => $plans])
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Activity Snapshot</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-6">Owners</dt>
                    <dd class="col-6 text-right">{{ $workshop['owners']->count() }}</dd>

                    <dt class="col-6">Team Members</dt>
                    <dd class="col-6 text-right">{{ $workshop['metrics']['staff_count'] }}</dd>

                    <dt class="col-6">Clients</dt>
                    <dd class="col-6 text-right">{{ $workshop['metrics']['client_count'] }}</dd>

                    <dt class="col-6">Created</dt>
                    <dd class="col-6 text-right">{{ optional($workshop['metrics']['created_at'])->format('M d, Y') ?? '—' }}</dd>

                    <dt class="col-6">Updated</dt>
                    <dd class="col-6 text-right">{{ optional($workshop['metrics']['updated_at'])->format('M d, Y') ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Linked Owners</h6>
            </div>
            <div class="card-body">
                @forelse ($workshop['owners'] as $owner)
                    <div class="mb-3">
                        <div class="font-weight-bold">{{ $owner->name }}</div>
                        <a href="mailto:{{ $owner->email }}">{{ $owner->email }}</a>
                    </div>
                @empty
                    <p class="text-muted mb-0">No owner associated. Assign one from the owner management screen.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
