@extends('layouts.guest')

@section('title', __('Join Existing Workshop'))

@section('content')
<div class="container">
    <h2 class="mb-3">{{ __('Workshop Already Exists') }}</h2>

    <p class="text-muted">{{ __('We found a workshop that matches the details you entered (duplicate :field). You can request staff access instead.', ['field' => __(data_get($state, 'staff.reason') === 'workshop_address' ? 'address' : 'name')]) }}</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-2">{{ $tenant ? data_get($tenant->data, 'workshop_name', $tenant->id) : data_get($state, 'workshop.workshop_name') }}</h5>
            <p class="mb-1"><strong>{{ __('Owner email:') }}</strong> {{ $tenant ? data_get($tenant->data, 'owner_email', '—') : '—' }}</p>
            <p class="mb-0"><strong>{{ __('Address:') }}</strong> {{ $tenant ? data_get($tenant->data, 'address', '—') : data_get($state, 'workshop.workshop_address', '—') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('register.staff.submit') }}">
        @csrf
        <div class="form-group">
            <label for="desired_role">{{ __('How will you help this workshop?') }}</label>
            <select name="desired_role" id="desired_role" class="form-control" required>
                <option value="">{{ __('Select role') }}</option>
                <option value="{{ \App\Models\User::ROLE_MECHANIC }}" {{ old('desired_role') === \App\Models\User::ROLE_MECHANIC ? 'selected' : '' }}>{{ __('Workshop Staff') }}</option>
                <option value="{{ \App\Models\User::ROLE_CLIENT }}" {{ old('desired_role') === \App\Models\User::ROLE_CLIENT ? 'selected' : '' }}>{{ __('Client Access') }}</option>
            </select>
        </div>
        <p class="text-muted">{{ __('We will notify the workshop owner to activate your access.') }}</p>
        <div class="d-flex justify-content-between">
            <a href="{{ route('register.workshop') }}" class="btn btn-light">{{ __('Back') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Request access') }}</button>
        </div>
    </form>
</div>
@endsection
