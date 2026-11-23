@extends('layouts.app')

@section('title', __($supplier->exists ? 'Edit supplier' : 'Add supplier'))

@section('content')
    <a href="{{ route('suppliers.index') }}">{{ __('Back to suppliers') }}</a>
    <h1 class="h3 mt-2 mb-3">{{ __($supplier->exists ? 'Edit supplier' : 'Add supplier') }}</h1>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}"
        method="POST" class="card shadow-sm p-3">
        @csrf
        @if ($supplier->exists)
            @method('PUT')
        @endif
        <div class="form-group">
            <label for="name">{{ __('Supplier name') }}</label>
            <input id="name" name="name" maxlength="255" class="form-control" value="{{ old('name', $supplier->name) }}" required>
        </div>
        <div class="form-group">
            <label for="contact_name">{{ __('Contact name (optional)') }}</label>
            <input id="contact_name" name="contact_name" maxlength="255" class="form-control"
                value="{{ old('contact_name', $supplier->contact_name) }}">
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="email">{{ __('Email (optional)') }}</label>
                <input id="email" name="email" type="email" maxlength="255" class="form-control"
                    value="{{ old('email', $supplier->email) }}">
            </div>
            <div class="form-group col-md-6">
                <label for="phone">{{ __('Phone (optional)') }}</label>
                <input id="phone" name="phone" maxlength="64" class="form-control"
                    value="{{ old('phone', $supplier->phone) }}">
            </div>
        </div>
        <div class="form-group">
            <label for="address">{{ __('Address (optional)') }}</label>
            <textarea id="address" name="address" maxlength="2000" rows="3" class="form-control">{{ old('address', $supplier->address) }}</textarea>
        </div>
        <input type="hidden" name="is_active" value="0">
        <div class="form-group form-check">
            <input id="is_active" name="is_active" type="checkbox" class="form-check-input" value="1"
                @checked((bool) old('is_active', $supplier->is_active ?? true))>
            <label class="form-check-label" for="is_active">{{ __('Active (can be selected for new purchase orders)') }}</label>
        </div>
        <div><button type="submit" class="btn btn-primary">{{ __('Save supplier') }}</button></div>
    </form>
@endsection
