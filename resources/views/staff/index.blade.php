@extends('layouts.app')

@section('title', 'Staff access')

@section('content')
<h1 class="h3 mb-3">Staff access</h1>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card p-3 mb-3">
    <h2 class="h5">Invite staff</h2>
    <p>Invitations expire in seven days. Existing accounts can be invited only if they are active, unassigned mechanics; they must sign in to accept. Workshop admins have full workshop access, including staff and payment settings.</p>
    <form method="POST" action="{{ route('staff.invite') }}">
        @csrf
        <div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control" required maxlength="255"></div>
        <div class="form-group"><label for="role">Role</label><select id="role" name="role" class="form-control"><option value="mechanic">Mechanic</option><option value="admin">Workshop admin</option></select></div>
        <div class="form-check mb-3"><input id="can_view_customers" name="can_view_customers" value="1" type="checkbox" class="form-check-input"><label for="can_view_customers" class="form-check-label">Allow mechanic to view customer and vehicle lists</label></div>
        <div class="form-check mb-3"><input id="can_create_job_invoices" name="can_create_job_invoices" value="1" type="checkbox" class="form-check-input"><label for="can_create_job_invoices" class="form-check-label">Allow mechanic to create invoices for their own completed jobs</label></div>
        <div class="form-check mb-3"><input id="can_record_payments" name="can_record_payments" value="1" type="checkbox" class="form-check-input"><label for="can_record_payments" class="form-check-label">Allow mechanic to view workshop invoices and record manual payments</label></div>
        <button type="submit" class="btn btn-primary">Send invitation</button>
    </form>
</div>
<div class="card p-3 mb-3">
    <h2 class="h5">Pending invitations</h2>
    @forelse ($invitations as $invitation)
        <div class="mb-2">{{ $invitation->email }} &middot; {{ $invitation->role }} &middot; Expires {{ $invitation->expires_at->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') }} MYT
            <form class="d-inline" method="POST" action="{{ route('staff.invitations.revoke', $invitation->id) }}">@csrf @method('DELETE') <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button></form>
        </div>
    @empty
        <p>No pending invitations.</p>
    @endforelse
</div>
<div class="card p-3 mb-3">
    <h2 class="h5">Staff registration requests</h2>
    @forelse ($requests as $member)
        <div class="border-bottom py-3">
            <strong>{{ $member->name }}</strong> ({{ $member->email }}) &middot; {{ $member->suspension_reason }}
            <form method="POST" action="{{ route('staff.requests.decide', $member->id) }}" class="mt-2">
                @csrf @method('PATCH')
                <label for="request-role-{{ $member->id }}">Approved role</label>
                <select id="request-role-{{ $member->id }}" name="role" class="form-control mb-2"><option value="mechanic">Mechanic</option><option value="admin">Workshop admin</option></select>
                <div class="form-check"><input id="request-customers-{{ $member->id }}" name="can_view_customers" value="1" type="checkbox" class="form-check-input"><label for="request-customers-{{ $member->id }}" class="form-check-label">View customer and vehicle lists</label></div>
                <div class="form-check"><input id="request-invoices-{{ $member->id }}" name="can_create_job_invoices" value="1" type="checkbox" class="form-check-input"><label for="request-invoices-{{ $member->id }}" class="form-check-label">Create invoices for own completed jobs</label></div>
                <div class="form-check mb-2"><input id="request-payments-{{ $member->id }}" name="can_record_payments" value="1" type="checkbox" class="form-check-input"><label for="request-payments-{{ $member->id }}" class="form-check-label">View workshop invoices and record manual payments</label></div>
                <button name="decision" value="approve" class="btn btn-sm btn-primary">Approve</button>
                <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
            </form>
        </div>
    @empty <p>No staff requests.</p> @endforelse
</div>
<div class="card p-3">
    <h2 class="h5">Staff</h2>
    @forelse ($staff as $member)
        <div class="border-bottom py-3">
            <strong>{{ $member->name }}</strong> ({{ $member->email }})
            @if ($member->id === auth()->id() || $member->id === (int) $ownerId || (! $ownerId && strtolower($member->email) === strtolower((string) $ownerEmail)))
                <span class="text-muted">&middot; {{ $member->role }} &middot; {{ $member->status }} (protected account)</span>
            @else
                <form method="POST" action="{{ route('staff.update', $member->id) }}" class="mt-2">
                    @csrf @method('PATCH')
                    <label for="role-{{ $member->id }}">Role</label>
                    <select id="role-{{ $member->id }}" name="role" class="form-control mb-2"><option value="mechanic" @selected($member->role === 'mechanic')>Mechanic</option><option value="admin" @selected($member->role === 'admin')>Workshop admin</option></select>
                    <label for="status-{{ $member->id }}">Status</label>
                    <select id="status-{{ $member->id }}" name="status" class="form-control mb-2"><option value="active" @selected($member->status === 'active')>Active</option><option value="suspended" @selected($member->status === 'suspended')>Suspended</option></select>
                    <div class="form-check mb-2"><input id="customers-{{ $member->id }}" name="can_view_customers" value="1" type="checkbox" class="form-check-input" @checked($member->can_view_customers)><label for="customers-{{ $member->id }}" class="form-check-label">Allow mechanic to view customer and vehicle lists</label></div>
                    <div class="form-check mb-2"><input id="invoices-{{ $member->id }}" name="can_create_job_invoices" value="1" type="checkbox" class="form-check-input" @checked($member->can_create_job_invoices)><label for="invoices-{{ $member->id }}" class="form-check-label">Allow mechanic to create invoices for own completed jobs</label></div>
                    <div class="form-check mb-2"><input id="payments-{{ $member->id }}" name="can_record_payments" value="1" type="checkbox" class="form-check-input" @checked($member->can_record_payments)><label for="payments-{{ $member->id }}" class="form-check-label">Allow mechanic to view workshop invoices and record manual payments</label></div>
                    <button class="btn btn-sm btn-primary" type="submit">Save access</button>
                </form>
            @endif
        </div>
    @empty
        <p>No staff found.</p>
    @endforelse
</div>
@endsection
