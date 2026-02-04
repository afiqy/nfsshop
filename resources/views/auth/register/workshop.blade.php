@extends('layouts.guest')

@section('title', 'Register - Workshop')

@section('content')
<div class="container">
    <h2 class="mb-4">Workshop Details</h2>

    <p class="text-muted">Step 2 of 3 — Tell us about the workshop you manage.</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register.workshop.submit') }}">
        @csrf
        <div class="form-group">
            <label for="workshop_name">Workshop name</label>
            <input type="text" id="workshop_name" name="workshop_name" class="form-control" value="{{ old('workshop_name', data_get($state, 'workshop.workshop_name')) }}" required>
        </div>
        <div class="form-group">
            <label for="workshop_address">Workshop address</label>
            <textarea id="workshop_address" name="workshop_address" class="form-control" rows="2" required>{{ old('workshop_address', data_get($state, 'workshop.workshop_address')) }}</textarea>
        </div>
        <div class="form-group">
            <label for="business_reg_no">Business registration number <span class="text-muted">(optional during trials)</span></label>
            <input type="text" id="business_reg_no" name="business_reg_no" class="form-control" value="{{ old('business_reg_no', data_get($state, 'workshop.business_reg_no')) }}">
        </div>
        <div class="form-group">
            <label for="tax_id_number">Tax Identification Number (TIN)</label>
            <input type="text" id="tax_id_number" name="tax_id_number" class="form-control" value="{{ old('tax_id_number', data_get($state, 'workshop.tax_id_number')) }}">
        </div>
        <div class="form-group">
            <label for="services_offered">Services offered <span class="text-muted">(comma separated)</span></label>
            <input type="text" id="services_offered" name="services_offered" class="form-control" value="{{ old('services_offered', implode(', ', data_get($state, 'workshop.services_offered', []))) }}">
        </div>
        <div class="d-flex justify-content-between">
            <a href="{{ route('register') }}" class="btn btn-light">Back</a>
            <button type="submit" class="btn btn-primary">Continue to plan selection</button>
        </div>
    </form>
</div>
@endsection
