@php
    $workshop = $workshop ?? [];
    $pricing = $workshop['pricing'] ?? [];
    $subscription = $workshop['subscription'] ?? [];
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="workshop_name">Workshop Name</label>
            <input type="text" class="form-control @error('workshop_name') is-invalid @enderror" id="workshop_name" name="workshop_name" value="{{ old('workshop_name', $workshop['name'] ?? '') }}" required>
            @error('workshop_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="owner_email">Primary Owner Email</label>
            <input type="email" class="form-control @error('owner_email') is-invalid @enderror" id="owner_email" name="owner_email" value="{{ old('owner_email', $workshop['owner_email'] ?? '') }}" required>
            @error('owner_email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="address">Address</label>
            <input type="text" class="form-control @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address', $workshop['address'] ?? '') }}">
            @error('address')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $workshop['phone'] ?? '') }}">
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="form-group">
    <label for="services">Services Offered <small class="text-muted">(comma separated)</small></label>
    <input type="text" class="form-control @error('services') is-invalid @enderror" id="services" name="services" value="{{ old('services', implode(', ', $workshop['services'] ?? [])) }}">
    @error('services')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label for="staff_notes">Team Notes</label>
    <textarea class="form-control @error('staff_notes') is-invalid @enderror" id="staff_notes" name="staff_notes" rows="3">{{ old('staff_notes', $workshop['staff_notes'] ?? '') }}</textarea>
    @error('staff_notes')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<hr>
<h5 class="mb-3">Pricing & Taxes</h5>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="currency">Currency</label>
            <input type="text" class="form-control @error('currency') is-invalid @enderror" id="currency" name="currency" value="{{ old('currency', strtoupper($pricing['currency'] ?? 'USD')) }}" maxlength="3" required>
            @error('currency')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="tax_rate">Tax Rate (%)</label>
            <input type="number" step="0.01" min="0" class="form-control @error('tax_rate') is-invalid @enderror" id="tax_rate" name="tax_rate" value="{{ old('tax_rate', $pricing['tax_rate'] ?? '') }}">
            @error('tax_rate')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="labor_rate">Labor Rate</label>
            <input type="number" step="0.01" min="0" class="form-control @error('labor_rate') is-invalid @enderror" id="labor_rate" name="labor_rate" value="{{ old('labor_rate', $pricing['labor_rate'] ?? '') }}">
            @error('labor_rate')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="callout_fee">Callout Fee</label>
            <input type="number" step="0.01" min="0" class="form-control @error('callout_fee') is-invalid @enderror" id="callout_fee" name="callout_fee" value="{{ old('callout_fee', $pricing['callout_fee'] ?? '') }}">
            @error('callout_fee')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="d-flex justify-content-end">
    <a href="{{ route(($accountArea ?? false) ? 'account.workshops.index' : 'super-admin.workshops.index') }}" class="btn btn-secondary mr-2">Cancel</a>
    <button type="submit" class="btn btn-primary">Save Changes</button>
</div>
