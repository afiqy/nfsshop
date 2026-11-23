@extends('layouts.app')

@section('title', __('Add Workshop'))

@section('content')
@php($workshopRoute = auth()->user()->isAccountSuperAdmin() ? 'account.workshops' : 'super-admin.workshops')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">{{ __('Add Workshop') }}</h1>
        <p class="mb-0 text-muted">{{ __('Create a new workshop and configure its details.') }}</p>
    </div>
    <a href="{{ route($workshopRoute.'.index') }}" class="btn btn-outline-secondary">{{ __('Back to workshops') }}</a>
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
                <form method="POST" action="{{ route($workshopRoute.'.store') }}">
                    @csrf
                    @include('super-admin.workshops._form', ['workshop' => $workshop, 'accountArea' => $accountArea ?? false])
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ __('Activity Snapshot') }}</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    @if(isset($workshop['owners']))
                    <dt class="col-6">{{ __('Owners') }}</dt>
                    <dd class="col-6 text-right">{{ $workshop['owners']->count() }}</dd>
                    @endif

                    @if(isset($workshop['metrics']));
                    <dt class="col-6">{{ __('Team Members') }}</dt>
                    <dd class="col-6 text-right">{{ $workshop['metrics']['staff_count'] }}</dd>

                    <dt class="col-6">{{ __('Clients') }}</dt>
                    <dd class="col-6 text-right">{{ $workshop['metrics']['client_count'] }}</dd>

                    <dt class="col-6">{{ __('Created') }}</dt>
                    <dd class="col-6 text-right">{{ optional($workshop['metrics']['created_at'])->locale(app()->getLocale())->translatedFormat('M d, Y') ?? '—' }}</dd>

                    <dt class="col-6">{{ __('Updated') }}</dt>
                    <dd class="col-6 text-right">{{ optional($workshop['metrics']['updated_at'])->locale(app()->getLocale())->translatedFormat('M d, Y') ?? '—' }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
