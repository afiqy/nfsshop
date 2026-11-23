@php
    $owner = $owner ?? null;
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="name">Owner Name</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', optional($owner)->name) }}" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', optional($owner)->email) }}" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="password">Password (at least 8 characters, up to 72 bytes) @if ($owner) <small class="text-muted">(leave blank to keep current)</small> @endif</label>
            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" @if (! $owner) required @endif>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="workshop_name">Workshop Name</label>
            <input type="text" class="form-control @error('workshop_name') is-invalid @enderror" id="workshop_name" name="workshop_name" value="{{ old('workshop_name', optional($owner)->workshop_name ?? optional(optional($owner)->tenant)->data['workshop_name'] ?? null) }}" required>
            @error('workshop_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="tenant_id">Tenant ID <small class="text-muted">(leave empty to auto-generate)</small></label>
            <input list="tenantOptions" type="text" class="form-control @error('tenant_id') is-invalid @enderror" id="tenant_id" name="tenant_id" value="{{ old('tenant_id', optional($owner)->tenant_id) }}">
            <datalist id="tenantOptions">
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}">{{ $tenant->id }}</option>
                @endforeach
            </datalist>
            @error('tenant_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="workshop_phone">Workshop Phone</label>
            <input type="text" class="form-control @error('workshop_phone') is-invalid @enderror" id="workshop_phone" name="workshop_phone" value="{{ old('workshop_phone', optional(optional($owner)->tenant)->data['phone'] ?? null) }}">
            @error('workshop_phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="form-group">
    <label for="workshop_address">Workshop Address</label>
    <input type="text" class="form-control @error('workshop_address') is-invalid @enderror" id="workshop_address" name="workshop_address" value="{{ old('workshop_address', optional(optional($owner)->tenant)->data['address'] ?? null) }}">
    @error('workshop_address')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="d-flex justify-content-end">
    <a href="{{ route('super-admin.owners.index') }}" class="btn btn-secondary mr-2">Cancel</a>
    <button type="submit" class="btn btn-primary">Save</button>
</div>
