@extends('layouts.app')

@section('title', __('Invoice :number', ['number' => $invoice->number()]))

@section('content')
    <a href="{{ route('client.invoices.index') }}">{{ __('Back to my invoices') }}</a>
    <h1 class="h3 mt-2">{{ __('Invoice :number', ['number' => $invoice->number()]) }}</h1>
    <p>{{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }}
        @if ($invoice->due_date)
            · {{ __('Due :date', ['date' => $invoice->due_date->locale(app()->getLocale())->translatedFormat('d M Y')]) }}
        @endif
    </p>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $paid = $invoice->paid_sen ?? 0;
        $refunded = $invoice->refunded_sen ?? 0;
        $credited = $invoice->credited_sen ?? 0;
        $balance = $invoice->balanceDue($paid, $refunded, $credited);
        $customerCredit = $invoice->customerCredit($paid, $refunded, $credited);
    @endphp
    <section class="card shadow-sm p-3 mb-4" aria-labelledby="invoice-summary">
        <h2 class="h5" id="invoice-summary">{{ __('Invoice summary') }}</h2>
        <p><strong>{{ __('Status:') }}</strong> {{ __($invoice->paymentStatus($paid, $refunded, $credited)) }}<br>
            <strong>{{ __('Total:') }}</strong> {{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}<br>
            <strong>{{ __('Payments received:') }}</strong> {{ \App\Models\Invoice::formatAmount($paid) }}<br>
            <strong>{{ __('Refunded:') }}</strong> {{ \App\Models\Invoice::formatAmount($refunded) }}<br>
            <strong>{{ __('Credits:') }}</strong> {{ \App\Models\Invoice::formatAmount($credited) }}<br>
            <strong>{{ __('Outstanding:') }}</strong> {{ \App\Models\Invoice::formatAmount($balance) }}<br>
            <strong>{{ __('Customer credit:') }}</strong> {{ \App\Models\Invoice::formatAmount($customerCredit) }}</p>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th scope="col">{{ __('Description') }}</th><th scope="col" class="text-right">{{ __('Quantity') }}</th><th scope="col" class="text-right">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td>{{ $item['description'] }}</td>
                            <td class="text-right">{{ $item['quantity'] }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($item['total_sen']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>@include('invoices.pricing-rows', ['labelColumns' => 2])</tfoot>
            </table>
        </div>
    </section>

    @if ($balance > 0 && ! $invoice->isVoided())
        <section class="card shadow-sm p-3 mb-4" aria-labelledby="payment-options">
            <h2 class="h5" id="payment-options">{{ __('Payment options') }}</h2>
            @if ($paymentQrs->isEmpty())
                <p>{{ __('The workshop has not added any QR payment options. Please contact the workshop.') }}</p>
            @else
                <p>{{ __('Pay using one of these workshop QR codes, then submit your payment proof. Submitting proof does not record a payment; the workshop must verify it first.') }}</p>
                <div class="row">
                    @foreach ($paymentQrs as $qr)
                        <div class="col-sm-6 col-lg-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h3 class="h6">{{ $qr->label }}</h3>
                                <p class="text-muted">{{ $qr->category === \App\Models\WorkshopPaymentQr::CATEGORY_BANK ? __('Bank') : __('E-wallet') }}</p>
                                <img class="img-fluid" src="{{ route('client.payment-qrs.image', $qr) }}" alt="{{ __(':label payment QR', ['label' => $qr->label]) }}">
                            </div>
                        </div>
                    @endforeach
                </div>
                <form action="{{ route('client.invoices.payment-submissions.store', $invoice) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="workshop_payment_qr_id">{{ __('QR payment method') }}</label>
                            <select class="form-control" id="workshop_payment_qr_id" name="workshop_payment_qr_id" required>
                                <option value="">{{ __('Select a method') }}</option>
                                @foreach ($paymentQrs as $qr)
                                    <option value="{{ $qr->id }}" @selected(old('workshop_payment_qr_id') == $qr->id)>{{ $qr->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="amount">{{ __('Amount paid (RM)') }}</label>
                            <input class="form-control" id="amount" name="amount" inputmode="decimal" maxlength="16"
                                pattern="[0-9]{1,13}(\.[0-9]{1,2})?" value="{{ is_string(old('amount')) ? old('amount') : '' }}" required>
                            <small class="form-text text-muted">{{ __('Partial payments are allowed. Available after pending proofs: :amount.', ['amount' => \App\Models\Invoice::formatAmount(max(0, $balance - $pendingAmount))]) }}</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="reference">{{ __('Transfer reference (optional)') }}</label>
                            <input class="form-control" id="reference" name="reference" maxlength="255" value="{{ is_string(old('reference')) ? old('reference') : '' }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="proof">{{ __('Payment proof (JPG, PNG, WebP or PDF; max 5 MB)') }}</label>
                        <input class="form-control-file" id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                    </div>
                    <button class="btn btn-primary" type="submit" @disabled($pendingAmount >= $balance)>{{ __('Submit payment proof') }}</button>
                </form>
            @endif
        </section>
    @endif

    @if ($creditNotes->isNotEmpty() || $refunds->isNotEmpty())
        <section class="card shadow-sm p-3 mb-4" aria-labelledby="adjustment-history">
            <h2 class="h5" id="adjustment-history">{{ __('Credit and refund history') }}</h2>
            <ul class="mb-0">
                @foreach ($creditNotes as $creditNote)
                    <li>{{ $creditNote->number() }} · {{ __('Credit') }} {{ \App\Models\Invoice::formatAmount($creditNote->amount_sen) }} · {{ $creditNote->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }} · {{ $creditNote->reason }}</li>
                @endforeach
                @foreach ($refunds as $refund)
                    <li>{{ $refund->number() }} · {{ __('Refund') }} {{ \App\Models\Invoice::formatAmount($refund->amount_sen) }} · {{ $refund->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }} · {{ $refund->reason }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($submissions->isNotEmpty())
        <section class="card shadow-sm p-3" aria-labelledby="payment-proofs">
            <h2 class="h5" id="payment-proofs">{{ __('Payment proof history') }}</h2>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th scope="col">{{ __('Submitted') }}</th><th scope="col">{{ __('Method') }}</th><th scope="col" class="text-right">{{ __('Amount') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Proof') }}</th><th scope="col">{{ __('Review') }}</th></tr></thead>
                    <tbody>
                        @foreach ($submissions as $submission)
                            <tr>
                                <td>{{ $submission->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ __($submission->payment_method) }}</td>
                                <td class="text-right">{{ \App\Models\Invoice::formatAmount($submission->amount_sen) }}</td>
                                <td>{{ __(ucfirst($submission->status)) }}</td>
                                <td><a href="{{ route('client.invoices.payment-submissions.proof', [$invoice, $submission]) }}">{{ __('Download') }}</a></td>
                                <td>{{ $submission->review_note ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
