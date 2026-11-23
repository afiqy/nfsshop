@extends('layouts.app')
@section('title', 'Staff invoice')
@section('content')
    <a href="{{ route('staff-invoices.index') }}">Back to invoices</a>
    <h1 class="h3 mt-2">Invoice {{ $invoice->number() }}</h1>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <p>{{ $invoice->customer_name }} &middot; {{ $invoice->vehicle_plate }} &middot; {{ $invoice->paymentStatus($invoice->paid_sen ?? 0, $invoice->refunded_sen ?? 0, $invoice->credited_sen ?? 0) }}</p>
    <table class="table">
        <thead><tr><th>Item</th><th>Quantity</th><th>Unit price</th><th>Amount</th></tr></thead>
        <tbody>@foreach ($invoice->items as $item)<tr><td>{{ $item['description'] }}</td><td>{{ $item['quantity'] }}</td><td>{{ \App\Models\Invoice::formatAmount($item['unit_price_sen']) }}</td><td>{{ \App\Models\Invoice::formatAmount($item['total_sen']) }}</td></tr>@endforeach</tbody>
        <tfoot>@include('invoices.pricing-rows', ['labelColumns' => 3])</tfoot>
    </table>
    <p>Total: {{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}. Balance: {{ \App\Models\Invoice::formatAmount($invoice->balanceDue($invoice->paid_sen ?? 0, $invoice->refunded_sen ?? 0, $invoice->credited_sen ?? 0)) }}.</p>
    <h2 class="h5">Recorded payments</h2>
    @forelse ($invoice->payments as $payment)
        <p>{{ \App\Models\Invoice::formatAmount($payment->amount_sen) }} &middot; {{ $payment->payment_method ?: 'Method not specified' }} &middot; {{ $payment->reference }}</p>
    @empty <p>No payments recorded.</p> @endforelse
    @if (auth()->user()->can_record_payments && ! $invoice->isVoided() && $invoice->balanceDue($invoice->paid_sen ?? 0, $invoice->refunded_sen ?? 0, $invoice->credited_sen ?? 0) > 0)
        <form method="POST" action="{{ route('staff-invoices.payments.store', $invoice) }}" class="card p-3">
            @csrf
            <h2 class="h5">Record a payment already received</h2>
            <p>Double-check the amount; recorded payments cannot be edited or deleted. QR payments with proof are handled by a workshop admin.</p>
            <label for="amount">Amount (RM)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" class="form-control mb-2" required>
            <label for="payment_method">Method</label><select id="payment_method" name="payment_method" class="form-control mb-2" required><option value="cash">Cash</option><option value="pos">POS</option><option value="transfer">Transfer</option></select>
            <label for="reference">Reference (optional)</label><input id="reference" name="reference" class="form-control mb-2" maxlength="255">
            <div><button class="btn btn-primary">Record payment</button></div>
        </form>
    @endif
@endsection
