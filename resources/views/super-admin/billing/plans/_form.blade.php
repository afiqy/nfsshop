@php
    $plan = $plan ?? null;
    $features = old('features', $plan ? implode("\n", $plan->features ?? []) : '');
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="name">Plan Name</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', optional($plan)->name) }}" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="price">Price</label>
            <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price', optional($plan)->price ?? '0.00') }}" required>
            @error('price')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="billing_interval">Interval</label>
            <select class="form-control @error('billing_interval') is-invalid @enderror" id="billing_interval" name="billing_interval" required>
                @foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('billing_interval', optional($plan)->billing_interval ?? 'monthly') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('billing_interval')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="trial_days">Trial Days</label>
            <input type="number" min="0" class="form-control @error('trial_days') is-invalid @enderror" id="trial_days" name="trial_days" value="{{ old('trial_days', optional($plan)->trial_days ?? 0) }}">
            @error('trial_days')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-9">
        <div class="form-group">
            <label for="description">Description</label>
            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="2">{{ old('description', optional($plan)->description) }}</textarea>
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="form-group">
    <label for="features">Included Features <small class="text-muted">One per line</small></label>
    <textarea class="form-control @error('features') is-invalid @enderror" id="features" name="features" rows="5">{{ $features }}</textarea>
    @error('features')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group form-check">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', optional($plan)->is_active ?? true))>
    <label class="form-check-label" for="is_active">Plan is active</label>
</div>

<div class="d-flex justify-content-end">
    <a href="{{ route('super-admin.billing.plans.index') }}" class="btn btn-secondary mr-2">Cancel</a>
    <button type="submit" class="btn btn-primary">Save Plan</button>
</div>
