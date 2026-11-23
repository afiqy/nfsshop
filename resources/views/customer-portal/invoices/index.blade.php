@extends('layouts.app')

@section('title', __('My invoices'))

@section('content')
    <a href="{{ route('client.portal') }}">{{ __('Back to my account') }}</a>
    <h1 class="h3 mt-2 mb-3">{{ __('My invoices') }}</h1>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th scope="col">{{ __('Invoice') }}</th><th scope="col">{{ __('Date') }}</th><th scope="col" class="text-right">{{ __('Total') }}</th><th scope="col" class="text-right">{{ __('Paid') }}</th><th scope="col" class="text-right">{{ __('Balance') }}</th><th scope="col">{{ __('Status') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @php($paid = $invoice->paid_sen ?? 0)
                        @php($refunded = $invoice->refunded_sen ?? 0)
                        @php($credited = $invoice->credited_sen ?? 0)
                        <tr>
                            <td><a href="{{ route('client.invoices.show', $invoice) }}">{{ $invoice->number() }}</a></td>
                            <td>{{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($paid) }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($invoice->balanceDue($paid, $refunded, $credited)) }}</td>
                            <td>{{ __($invoice->paymentStatus($paid, $refunded, $credited)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">{{ __('No invoices are linked to your account.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $invoices->links() }}</div>
@endsection
