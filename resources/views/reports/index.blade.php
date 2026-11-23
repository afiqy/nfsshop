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
    @if ($grossServiceError)
        <div class="alert alert-danger">Gross service reporting is unavailable: {{ $grossServiceError }} Check the invoice before relying on this report.</div>
    @else
    <div class="table-responsive"><table class="table"><thead><tr><th>Service</th><th>Quantity</th><th>Gross amount</th></tr></thead><tbody>
        @forelse ($services as $service)
            <tr><td>{{ $service['name'] }} (#{{ $service['id'] }})</td><td>{{ $service['quantity'] }}</td><td>{{ $money($service['gross_sen']) }}</td></tr>
        @empty <tr><td colspan="3">No tagged service lines in this period.</td></tr> @endforelse
    </tbody></table></div>
    {{ $services->fragment('service-report')->links() }}
    <p>Unclassified gross lines: {{ $money($unclassified) }}</p>
    @endif
</section>
<section class="card p-3 mb-3" id="net-service-report">
    <h2 class="h5">Net revenue by service</h2>
    <p>Accrual basis: invoice net amounts are counted when issued and credit notes when issued, including credits for earlier invoices. Invoice-wide discounts, tax and credits are allocated proportionally across all lines with deterministic sen rounding. Tax and cash refunds are excluded. Untagged historical and inventory lines are unclassified; they are not guessed or backfilled. Voided invoices are excluded based on current status. This is an operational estimate, not an accounting report.</p>
    @if ($netServiceError)
        <div class="alert alert-danger">Net service revenue is unavailable: {{ $netServiceError }} Check the invoice and its credit notes before relying on this report.</div>
    @else
        <div class="table-responsive"><table class="table"><thead><tr><th>Service</th><th>Invoiced excluding tax</th><th>Credits excluding tax</th><th>Net revenue</th></tr></thead><tbody>
            @forelse ($netServices as $service)
                <tr><td>{{ $service['name'] }} (#{{ $service['id'] }})</td><td>{{ $money($service['invoiced_sen']) }}</td><td>{{ $money($service['credited_sen']) }}</td><td>{{ $money($service['invoiced_sen'] - $service['credited_sen']) }}</td></tr>
            @empty <tr><td colspan="4">No tagged service revenue in this period.</td></tr> @endforelse
            <tr><th>Unclassified</th><td>{{ $money($netUnclassified['invoiced_sen']) }}</td><td>{{ $money($netUnclassified['credited_sen']) }}</td><td>{{ $money($netUnclassified['invoiced_sen'] - $netUnclassified['credited_sen']) }}</td></tr>
            <tr><th>Total (all pages)</th><td>{{ $money($netTotals['invoiced_sen']) }}</td><td>{{ $money($netTotals['credited_sen']) }}</td><td>{{ $money($netTotals['invoiced_sen'] - $netTotals['credited_sen']) }}</td></tr>
        </tbody></table></div>
        {{ $netServices->fragment('net-service-report')->links() }}
    @endif
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
