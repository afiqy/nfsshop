@extends('layouts.app')
@section('title', __('Service'))
@section('content')
<a href="{{ route('services.index') }}">{{ __('Back to services') }}</a>
<h1 class="h3 mt-2">{{ __($service->exists ? 'Edit service' : 'Add service') }}</h1>
@if ($errors->any())<div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ $service->exists ? route('services.update', $service->id) : route('services.store') }}" method="POST" class="card p-3">
    @csrf @if ($service->exists) @method('PUT') @endif
    <div class="form-group"><label for="name">{{ __('Name') }}</label><input id="name" name="name" class="form-control" maxlength="255" required value="{{ old('name', $service->name) }}"></div>
    <div class="form-group"><label for="price">{{ __('Price (RM)') }}</label><input id="price" name="price" type="number" min="0" max="999999.99" step="0.01" class="form-control" required value="{{ old('price', $service->exists ? number_format($service->price_sen / 100, 2, '.', '') : '') }}"></div>
    <div class="form-check mb-3"><input id="is_active" name="is_active" value="1" type="checkbox" class="form-check-input" @checked(old('is_active', $service->exists ? $service->is_active : true))><label for="is_active" class="form-check-label">{{ __('Active') }}</label></div>
    <button class="btn btn-primary">{{ __('Save service') }}</button>
</form>
@endsection
