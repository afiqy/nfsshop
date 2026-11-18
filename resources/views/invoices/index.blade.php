@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Invoices</h1>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">New invoice</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Invoice</th>
                        <th scope="col">Date</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Vehicle</th>
                        <th scope="col" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number() }}</a></td>
                            <td>{{ $invoice->created_at->format('d M Y') }}</td>
                            <td>{{ $invoice->customer_name }}</td>
                            <td>{{ $invoice->vehicle_plate ?? '—' }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $invoices->links() }}</div>
@endsection
