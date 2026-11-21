@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Invoices</h1>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">New invoice</a>
    </div>

    <nav class="mb-3" aria-label="Filter invoices">
        <a class="btn {{ $overdueOnly ? 'btn-outline-secondary' : 'btn-secondary' }}" href="{{ route('invoices.index') }}"
            @if (! $overdueOnly) aria-current="page" @endif>All invoices</a>
        <a class="btn {{ $overdueOnly ? 'btn-danger' : 'btn-outline-danger' }}" href="{{ route('invoices.index', ['status' => 'overdue']) }}"
            @if ($overdueOnly) aria-current="page" @endif>Overdue only</a>
    </nav>
    <p><a href="{{ route('myinvois.seller.edit') }}">MyInvois workshop tax profile</a></p>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Invoice</th>
                        <th scope="col">Date</th>
                        <th scope="col">Due date</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Vehicle</th>
                        <th scope="col" class="text-right">Total</th>
                        <th scope="col" class="text-right">Balance</th>
                        <th scope="col">Payment status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number() }}</a></td>
                            <td>{{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }}</td>
                            <td>{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $invoice->customer_name }}</td>
                            <td>{{ $invoice->vehicle_plate ?? '—' }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($invoice->balanceDue($invoice->paid_sen ?? 0)) }}</td>
                            <td>
                                {{ $invoice->paymentStatus($invoice->paid_sen ?? 0) }}
                                @if ($invoice->isOverdue($invoice->paid_sen ?? 0, $today))
                                    <span class="badge badge-danger">Overdue</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">{{ $overdueOnly ? 'No overdue invoices.' : 'No invoices yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $invoices->links() }}</div>
@endsection
