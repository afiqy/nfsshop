@extends('layouts.guest')

@section('title', __('Register - Plan'))

@section('content')
<div class="container">
    <h2 class="mb-4">{{ __('Choose Your Plan') }}</h2>

    <p class="text-muted">{{ __('Step 3 of 3 — Pick the package that matches your workshop.') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register.plan.submit') }}">
        @csrf
        <div class="form-group">
            <label>{{ __('Plan tier') }}</label>
            @foreach (['basic' => 'Basic', 'pro' => 'Pro'] as $value => $label)
                <div class="custom-control custom-radio">
                    <input type="radio" id="plan_tier_{{ $value }}" name="plan_tier" class="custom-control-input" value="{{ $value }}" {{ old('plan_tier', data_get($state, 'plan.tier', 'basic')) === $value ? 'checked' : '' }}>
                    <label class="custom-control-label" for="plan_tier_{{ $value }}">{{ __($label) }}</label>
                </div>
            @endforeach
        </div>

        <div class="form-group">
            <label>{{ __('Billing preference') }}</label>
            @foreach ($billingOptions as $value => $label)
                <div class="custom-control custom-radio">
                    <input type="radio" id="billing_{{ $value }}" name="billing_option" class="custom-control-input" value="{{ $value }}" {{ old('billing_option', data_get($state, 'plan.billing_option')) === $value ? 'checked' : '' }} required>
                    <label class="custom-control-label" for="billing_{{ $value }}">{{ __($label) }}</label>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('register.workshop') }}" class="btn btn-light">{{ __('Back') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Create my workshop') }}</button>
        </div>
    </form>
</div>
@endsection
