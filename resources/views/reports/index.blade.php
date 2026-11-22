@extends('layouts.app')
@section('title', 'Workshop reports')
@section('content')
@php
    $money = fn ($amount) => ($amount < 0 ? '-' : '').\App\Models\Invoice::formatAmount(abs((int) $amount));
@endphp
<h1 class="h3">Workshop reports</h1>
<form method="GET" action="{{ route('reports.index') }}" class="form-inline mb-3">
    <label for="period" class="mr-2">Malaysia-time month (YYYY-MM) or year (YYYY)</label>
    <input id="period" name="period" class="form-control mr-2" value="{{ $period }}" pattern="20[0-9]{2}(-(0[1-9]|1[0-2]))?" required>
    <button class="btn btn-primary">Show report</button>
</form>
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<p>Period: {{ $period }} (Malaysia time). Amounts are operational figures, not accounting or MyInvois reports.</p>
<section class="card p-3 mb-3">
    <h2 class="h5">Invoicing and cash movement</h2>
    <p>Invoices use their creation date and current issued status. Credit notes, payments and refunds use their own recorded dates, even when the invoice was issued in another period. Invoice totals include any invoice discount and tax.</p>
    <dl class="row mb-0">
        <dt class="col-sm-6">Issued invoices ({{ $invoiceCount }})</dt><dd class="col-sm-6">{{ $money($invoiced) }}</dd>
        <dt class="col-sm-6">Credit notes issued</dt><dd class="col-sm-6">{{ $money($credited) }}</dd>
        <dt class="col-sm-6">Invoiced less credits</dt><dd class="col-sm-6">{{ $money($invoiced - $credited) }}</dd>
        <dt class="col-sm-6">Payments received</dt><dd class="col-sm-6">{{ $money($received) }}</dd>
        <dt class="col-sm-6">Refunds recorded</dt><dd class="col-sm-6">{{ $money($refunded) }}</dd>
        <dt class="col-sm-6">Payments less refunds</dt><dd class="col-sm-6">{{ $money($received - $refunded) }}</dd>
    </dl>
</section>
<section class="card p-3 mb-3" id="service-report">
    <h2 class="h5">Gross service lines</h2>
    <p>Tagged services on invoices issued in the period, before invoice-wide discounts and credit notes. Included tax is extracted per line and rounded to the nearest sen; added tax is excluded. These figures are not net revenue and do not allocate payments or refunds. Untagged lines, including historical invoices and inventory, are unclassified.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Service</th><th>Quantity</th><th>Gross amount</th></tr></thead><tbody>
        @forelse ($services as $service)
            <tr><td>{{ $service['name'] }} (#{{ $service['id'] }})</td><td>{{ $service['quantity'] }}</td><td>{{ $money($service['gross_sen']) }}</td></tr>
        @empty <tr><td colspan="3">No tagged service lines in this period.</td></tr> @endforelse
    </tbody></table></div>
    {{ $services->fragment('service-report')->links() }}
    <p>Unclassified gross lines: {{ $money($unclassified) }}</p>
</section>
<section class="card p-3 mb-3" id="customer-report">
    <h2 class="h5">Customer spend</h2>
    <p>Customers ranked by payments less refunds recorded in this period, including payments for invoices from earlier periods. A negative value means refunds exceeded payments in the period.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Customer</th><th>Payments less refunds</th></tr></thead><tbody>
        @forelse ($customers as $customer)
            <tr><td>{{ $customer->name ?? 'Deleted or unlinked customer' }}</td><td>{{ $money($customer->net_sen) }}</td></tr>
        @empty <tr><td colspan="2">No customer cash movement in this period.</td></tr> @endforelse
    </tbody></table></div>
    {{ $customers->fragment('customer-report')->links() }}
</section>
<section class="card p-3" id="staff-report">
    <h2 class="h5">Completed jobs by assigned mechanic</h2>
    <p>Jobs completed during this period are credited to the mechanic assigned at completion. Unassigned jobs are listed separately; this is a count, not a measure of quality or hours.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Mechanic</th><th>Completed jobs</th></tr></thead><tbody>
        @forelse ($staff as $member)
            <tr><td>{{ $member->assigned_mechanic_id ? ($staffNames[$member->assigned_mechanic_id] ?? 'Former or unavailable staff #'.$member->assigned_mechanic_id) : 'Unassigned' }}</td><td>{{ $member->completed_count }}</td></tr>
        @empty <tr><td colspan="2">No completed jobs in this period.</td></tr> @endforelse
    </tbody></table></div>
    {{ $staff->fragment('staff-report')->links() }}
</section>
@endsection
