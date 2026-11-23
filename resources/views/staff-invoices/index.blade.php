@extends('layouts.app')
@section('title', 'Staff invoices')
@section('content')
    <h1 class="h3">Invoices</h1>
    <p>{{ auth()->user()->can_record_payments ? 'Workshop invoices for recording manual payments.' : 'Invoices linked to your assigned jobs.' }}</p>
    @forelse ($invoices as $invoice)
        <div class="card p-3 mb-2">
            <a href="{{ route('staff-invoices.show', $invoice) }}">{{ $invoice->number() }}</a>
            <div>{{ $invoice->customer_name }} &middot; {{ $invoice->vehicle_plate }} &middot;
                {{ $invoice->paymentStatus($invoice->paid_sen ?? 0, $invoice->refunded_sen ?? 0, $invoice->credited_sen ?? 0) }}
                &middot; Balance {{ \App\Models\Invoice::formatAmount($invoice->balanceDue($invoice->paid_sen ?? 0, $invoice->refunded_sen ?? 0, $invoice->credited_sen ?? 0)) }}</div>
        </div>
    @empty <p>No invoices found.</p> @endforelse
    {{ $invoices->links() }}
@endsection
