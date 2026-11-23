@extends('layouts.app')

@section('title', __('Staff access'))

@section('content')
<h1 class="h3 mb-3">{{ __('Staff access') }}</h1>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card p-3 mb-3">
    <h2 class="h5">{{ __('Invite staff') }}</h2>
    <p>{{ __('Invitations expire in seven days. Existing accounts can be invited only if they are active, unassigned mechanics; they must sign in to accept. Workshop admins have full workshop access, including staff and payment settings.') }}</p>
    <form method="POST" action="{{ route('staff.invite') }}">
        @csrf
        <div class="form-group"><label for="email">{{ __('Email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control" required maxlength="255"></div>
        <div class="form-group"><label for="role">{{ __('Role') }}</label><select id="role" name="role" class="form-control"><option value="mechanic">{{ __('Mechanic') }}</option><option value="admin">{{ __('Workshop admin') }}</option></select></div>
        <div class="form-check mb-3"><input id="can_view_customers" name="can_view_customers" value="1" type="checkbox" class="form-check-input"><label for="can_view_customers" class="form-check-label">{{ __('Allow mechanic to view customer and vehicle lists') }}</label></div>
        <div class="form-check mb-3"><input id="can_create_job_invoices" name="can_create_job_invoices" value="1" type="checkbox" class="form-check-input"><label for="can_create_job_invoices" class="form-check-label">{{ __('Allow mechanic to create invoices for their own completed jobs') }}</label></div>
        <div class="form-check mb-3"><input id="can_record_payments" name="can_record_payments" value="1" type="checkbox" class="form-check-input"><label for="can_record_payments" class="form-check-label">{{ __('Allow mechanic to view workshop invoices and record manual payments') }}</label></div>
        <div class="form-check mb-3"><input id="can_view_vehicle_history" name="can_view_vehicle_history" value="1" type="checkbox" class="form-check-input"><label for="can_view_vehicle_history" class="form-check-label">{{ __('View all workshop vehicle history, including notes and invoice totals') }}</label></div>
        <div class="form-check mb-3"><input id="can_create_customer_records" name="can_create_customer_records" value="1" type="checkbox" class="form-check-input"><label for="can_create_customer_records" class="form-check-label">{{ __('Create customer and vehicle records') }}</label></div>
        <div class="form-check mb-3"><input id="can_review_appointments" name="can_review_appointments" value="1" type="checkbox" class="form-check-input"><label for="can_review_appointments" class="form-check-label">{{ __('Confirm or reject workshop appointment requests') }}</label></div>
        <button type="submit" class="btn btn-primary">{{ __('Send invitation') }}</button>
    </form>
</div>
<div class="card p-3 mb-3">
    <h2 class="h5">{{ __('Pending invitations') }}</h2>
    @forelse ($invitations as $invitation)
        <div class="mb-2">{{ $invitation->email }} &middot; {{ __($invitation->role === 'admin' ? 'Workshop admin' : 'Mechanic') }} &middot; {{ __('Expires :date MYT', ['date' => $invitation->expires_at->timezone('Asia/Kuala_Lumpur')->locale(app()->getLocale())->translatedFormat('d M Y H:i')]) }}
            <form class="d-inline" method="POST" action="{{ route('staff.invitations.revoke', $invitation->id) }}">@csrf @method('DELETE') <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Revoke') }}</button></form>
        </div>
    @empty
        <p>{{ __('No pending invitations.') }}</p>
    @endforelse
</div>
<div class="card p-3 mb-3">
    <h2 class="h5">{{ __('Staff registration requests') }}</h2>
    @forelse ($requests as $member)
        <div class="border-bottom py-3">
            <strong>{{ $member->name }}</strong> ({{ $member->email }}) &middot; {{ $member->suspension_reason }}
            <form method="POST" action="{{ route('staff.requests.decide', $member->id) }}" class="mt-2">
                @csrf @method('PATCH')
                <label for="request-role-{{ $member->id }}">{{ __('Approved role') }}</label>
                <select id="request-role-{{ $member->id }}" name="role" class="form-control mb-2"><option value="mechanic">{{ __('Mechanic') }}</option><option value="admin">{{ __('Workshop admin') }}</option></select>
                <div class="form-check"><input id="request-customers-{{ $member->id }}" name="can_view_customers" value="1" type="checkbox" class="form-check-input"><label for="request-customers-{{ $member->id }}" class="form-check-label">{{ __('View customer and vehicle lists') }}</label></div>
                <div class="form-check"><input id="request-invoices-{{ $member->id }}" name="can_create_job_invoices" value="1" type="checkbox" class="form-check-input"><label for="request-invoices-{{ $member->id }}" class="form-check-label">{{ __('Create invoices for own completed jobs') }}</label></div>
                <div class="form-check mb-2"><input id="request-payments-{{ $member->id }}" name="can_record_payments" value="1" type="checkbox" class="form-check-input"><label for="request-payments-{{ $member->id }}" class="form-check-label">{{ __('View workshop invoices and record manual payments') }}</label></div>
                <div class="form-check"><input id="request-history-{{ $member->id }}" name="can_view_vehicle_history" value="1" type="checkbox" class="form-check-input"><label for="request-history-{{ $member->id }}" class="form-check-label">{{ __('View all workshop vehicle history, including notes and invoice totals') }}</label></div>
                <div class="form-check"><input id="request-create-{{ $member->id }}" name="can_create_customer_records" value="1" type="checkbox" class="form-check-input"><label for="request-create-{{ $member->id }}" class="form-check-label">{{ __('Create customer and vehicle records') }}</label></div>
                <div class="form-check mb-2"><input id="request-appointments-{{ $member->id }}" name="can_review_appointments" value="1" type="checkbox" class="form-check-input"><label for="request-appointments-{{ $member->id }}" class="form-check-label">{{ __('Confirm or reject workshop appointment requests') }}</label></div>
                <button name="decision" value="approve" class="btn btn-sm btn-primary">{{ __('Approve') }}</button>
                <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">{{ __('Reject') }}</button>
            </form>
        </div>
    @empty <p>{{ __('No staff requests.') }}</p> @endforelse
</div>
<div class="card p-3">
    <h2 class="h5">{{ __('Staff') }}</h2>
    @forelse ($staff as $member)
        <div class="border-bottom py-3">
            <strong>{{ $member->name }}</strong> ({{ $member->email }})
            @if ($member->id === auth()->id() || $member->id === (int) $ownerId || (! $ownerId && strtolower($member->email) === strtolower((string) $ownerEmail)))
                <span class="text-muted">&middot; {{ __($member->role === 'admin' ? 'Workshop admin' : 'Mechanic') }} &middot; {{ __(ucfirst($member->status)) }} ({{ __('protected account') }})</span>
            @else
                <form method="POST" action="{{ route('staff.update', $member->id) }}" class="mt-2">
                    @csrf @method('PATCH')
                    <label for="role-{{ $member->id }}">{{ __('Role') }}</label>
                    <select id="role-{{ $member->id }}" name="role" class="form-control mb-2"><option value="mechanic" @selected($member->role === 'mechanic')>{{ __('Mechanic') }}</option><option value="admin" @selected($member->role === 'admin')>{{ __('Workshop admin') }}</option></select>
                    <label for="status-{{ $member->id }}">{{ __('Status') }}</label>
                    <select id="status-{{ $member->id }}" name="status" class="form-control mb-2"><option value="active" @selected($member->status === 'active')>{{ __('Active') }}</option><option value="suspended" @selected($member->status === 'suspended')>{{ __('Suspended') }}</option></select>
                    <div class="form-check mb-2"><input id="customers-{{ $member->id }}" name="can_view_customers" value="1" type="checkbox" class="form-check-input" @checked($member->can_view_customers)><label for="customers-{{ $member->id }}" class="form-check-label">{{ __('Allow mechanic to view customer and vehicle lists') }}</label></div>
                    <div class="form-check mb-2"><input id="invoices-{{ $member->id }}" name="can_create_job_invoices" value="1" type="checkbox" class="form-check-input" @checked($member->can_create_job_invoices)><label for="invoices-{{ $member->id }}" class="form-check-label">{{ __('Allow mechanic to create invoices for their own completed jobs') }}</label></div>
                    <div class="form-check mb-2"><input id="payments-{{ $member->id }}" name="can_record_payments" value="1" type="checkbox" class="form-check-input" @checked($member->can_record_payments)><label for="payments-{{ $member->id }}" class="form-check-label">{{ __('Allow mechanic to view workshop invoices and record manual payments') }}</label></div>
                    <div class="form-check mb-2"><input id="history-{{ $member->id }}" name="can_view_vehicle_history" value="1" type="checkbox" class="form-check-input" @checked($member->can_view_vehicle_history)><label for="history-{{ $member->id }}" class="form-check-label">{{ __('View all workshop vehicle history, including notes and invoice totals') }}</label></div>
                    <div class="form-check mb-2"><input id="create-records-{{ $member->id }}" name="can_create_customer_records" value="1" type="checkbox" class="form-check-input" @checked($member->can_create_customer_records)><label for="create-records-{{ $member->id }}" class="form-check-label">{{ __('Create customer and vehicle records') }}</label></div>
                    <div class="form-check mb-2"><input id="appointments-{{ $member->id }}" name="can_review_appointments" value="1" type="checkbox" class="form-check-input" @checked($member->can_review_appointments)><label for="appointments-{{ $member->id }}" class="form-check-label">{{ __('Confirm or reject workshop appointment requests') }}</label></div>
                    <button class="btn btn-sm btn-primary" type="submit">{{ __('Save access') }}</button>
                </form>
            @endif
        </div>
    @empty
        <p>{{ __('No staff found.') }}</p>
    @endforelse
</div>
@endsection
