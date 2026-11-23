@extends('layouts.app')

@section('title', __('Invoices'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Invoices') }}</h1>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">{{ __('New invoice') }}</a>
    </div>

    @if (session('success'))
        <p role="status">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <section class="mb-3" aria-labelledby="reminder-settings-heading">
        <h2 class="h5" id="reminder-settings-heading">{{ __('Payment reminders') }}</h2>
        <p>{{ __('Email reminders are sent on the due date and 7 and 14 days overdue, only while a balance remains. Pending payment proofs pause reminders. Failed attempts can be retried from the invoice.') }}</p>
        <form action="{{ route('payment-reminders.settings') }}" method="POST">
            @csrf
            @method('PUT')
            <label for="reminders-enabled">{{ __('Automatic reminders') }}</label>
            <select id="reminders-enabled" name="enabled">
                <option value="0" @selected(! $remindersEnabled)>{{ __('Off') }}</option>
                <option value="1" @selected($remindersEnabled)>{{ __('On') }}</option>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary">{{ __('Save reminder setting') }}</button>
        </form>
    </section>
    <p>
        <a href="{{ route('payment-submissions.index') }}">{{ __('Pending payment proofs') }}</a>
        <span class="badge badge-secondary">{{ $pendingPaymentProofs }}</span>
    </p>
    <nav class="mb-3" aria-label="{{ __('Filter invoices') }}">
        <a class="btn {{ $overdueOnly ? 'btn-outline-secondary' : 'btn-secondary' }}" href="{{ route('invoices.index') }}"
            @if (! $overdueOnly) aria-current="page" @endif>{{ __('All invoices') }}</a>
        <a class="btn {{ $overdueOnly ? 'btn-danger' : 'btn-outline-danger' }}" href="{{ route('invoices.index', ['status' => 'overdue']) }}"
            @if ($overdueOnly) aria-current="page" @endif>{{ __('Overdue only') }}</a>
    </nav>
    @include('exports.controls', [
        'id' => 'invoices-export',
        'csvUrl' => route('exports.index', ['type' => 'invoices', 'format' => 'csv'] + ($overdueOnly ? ['status' => 'overdue'] : [])),
        'pdfUrl' => route('exports.index', ['type' => 'invoices', 'format' => 'pdf']),
        'filters' => $overdueOnly ? ['status' => 'overdue'] : [],
    ])
    <h2 class="h5">{{ __('Payment records') }}</h2>
    @include('exports.controls', [
        'id' => 'payments-export',
        'csvUrl' => route('exports.index', ['type' => 'payments', 'format' => 'csv']),
        'pdfUrl' => route('exports.index', ['type' => 'payments', 'format' => 'pdf']),
        'filters' => [],
    ])
    <p><a href="{{ route('myinvois.seller.edit') }}">{{ __('MyInvois workshop tax profile') }}</a></p>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Invoice') }}</th>
                        <th scope="col">{{ __('Date') }}</th>
                        <th scope="col">{{ __('Due date') }}</th>
                        <th scope="col">{{ __('Customer') }}</th>
                        <th scope="col">{{ __('Vehicle') }}</th>
                        <th scope="col" class="text-right">{{ __('Total') }}</th>
                        <th scope="col" class="text-right">{{ __('Balance') }}</th>
                        <th scope="col">{{ __('Payment status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @php
                            $paid = $invoice->paid_sen ?? 0;
                            $refunded = $invoice->refunded_sen ?? 0;
                            $credited = $invoice->credited_sen ?? 0;
                        @endphp
                        <tr>
                            <td><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number() }}</a></td>
                            <td>{{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }}</td>
                            <td>{{ $invoice->due_date?->locale(app()->getLocale())->translatedFormat('d M Y') ?? '—' }}</td>
                            <td>{{ $invoice->customer_name }}</td>
                            <td>{{ $invoice->vehicle_plate ?? '—' }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($invoice->balanceDue($paid, $refunded, $credited)) }}</td>
                            <td>
                                {{ __($invoice->paymentStatus($paid, $refunded, $credited)) }}
                                @if ($invoice->isOverdue($paid, $today, $refunded, $credited))
                                    <span class="badge badge-danger">{{ __('Overdue') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">{{ __($overdueOnly ? 'No overdue invoices.' : 'No invoices yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $invoices->links() }}</div>
@endsection
