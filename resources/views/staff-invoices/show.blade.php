@extends('layouts.app')
@section('title', __('Staff invoice'))
@section('content')
    <a href="{{ route('staff-invoices.index') }}">{{ __('Back to invoices') }}</a>
    <h1 class="h3 mt-2">{{ __('Invoice :number', ['number' => $invoice->number()]) }}</h1>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <p>{{ $invoice->customer_name }} &middot; {{ $invoice->vehicle_plate }} &middot; {{ __($invoice->paymentStatus($invoice->paid_sen ?? 0, $invoice->refunded_sen ?? 0, $invoice->credited_sen ?? 0)) }}</p>
    <table class="table">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Unit price') }}</th><th>{{ __('Amount') }}</th></tr></thead>
        <tbody>@foreach ($invoice->items as $item)<tr><td>{{ $item['description'] }}</td><td>{{ $item['quantity'] }}</td><td>{{ \App\Models\Invoice::formatAmount($item['unit_price_sen']) }}</td><td>{{ \App\Models\Invoice::formatAmount($item['total_sen']) }}</td></tr>@endforeach</tbody>
        <tfoot>@include('invoices.pricing-rows', ['labelColumns' => 3])</tfoot>
    </table>
    <p>{{ __('Total:') }} {{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}. {{ __('Balance:') }} {{ \App\Models\Invoice::formatAmount($invoice->balanceDue($invoice->paid_sen ?? 0, $invoice->refunded_sen ?? 0, $invoice->credited_sen ?? 0)) }}.</p>
    <h2 class="h5">{{ __('Recorded payments') }}</h2>
    @forelse ($invoice->payments as $payment)
        <p>{{ \App\Models\Invoice::formatAmount($payment->amount_sen) }} &middot; {{ $payment->payment_method ? ($payment->payment_method === 'pos' ? __('POS') : __(ucfirst($payment->payment_method))) : __('Method not specified') }} &middot; {{ $payment->reference }}</p>
    @empty <p>{{ __('No payments recorded.') }}</p> @endforelse
    @if (auth()->user()->can_record_payments && ! $invoice->isVoided() && $invoice->balanceDue($invoice->paid_sen ?? 0, $invoice->refunded_sen ?? 0, $invoice->credited_sen ?? 0) > 0)
        <form method="POST" action="{{ route('staff-invoices.payments.store', $invoice) }}" class="card p-3">
            @csrf
            <h2 class="h5">{{ __('Record a payment already received') }}</h2>
            <p>{{ __('Double-check the amount; recorded payments cannot be edited or deleted. QR payments with proof are handled by a workshop admin.') }}</p>
            <label for="amount">{{ __('Amount (RM)') }}</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" class="form-control mb-2" required>
            <label for="payment_method">{{ __('Method') }}</label><select id="payment_method" name="payment_method" class="form-control mb-2" required><option value="cash">{{ __('Cash') }}</option><option value="pos">{{ __('POS') }}</option><option value="transfer">{{ __('Transfer') }}</option></select>
            <label for="reference">{{ __('Reference (optional)') }}</label><input id="reference" name="reference" class="form-control mb-2" maxlength="255">
            <div><button class="btn btn-primary">{{ __('Record payment') }}</button></div>
        </form>
    @endif
@endsection
