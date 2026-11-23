<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Invoice :number', ['number' => $invoice->number()]) }}</title>
    <style>
        body { margin: 2rem auto; max-width: 52rem; padding: 0 1rem; font: 1rem/1.5 system-ui, sans-serif; color: #242424; }
        nav { display: flex; gap: 1rem; align-items: center; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .5rem; border-bottom: 1px solid #ccc; text-align: left; }
        .amount { text-align: right; white-space: nowrap; }
        .address { white-space: pre-line; }
        .description { overflow-wrap: anywhere; }
        .void-stamp { border: 3px solid #a00; color: #a00; display: inline-block; font-size: 1.5rem; font-weight: bold; padding: .25rem .75rem; transform: rotate(-4deg); }
        @media print {
            nav, [role="status"], #payments, #payment-reminders, #invoice-email { display: none; }
            body { margin: 0; max-width: none; }
            thead { display: table-header-group; }
            tfoot { display: table-row-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <nav>
        <a href="{{ route('invoices.index') }}">{{ __('Back to invoices') }}</a>
        @if ($canEdit)
            <a href="{{ route('invoices.edit', $invoice) }}">{{ __('Edit invoice') }}</a>
        @endif
        <a href="{{ route('myinvois.buyer.edit', $invoice) }}">{{ __('MyInvois preparation') }}</a>
        <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </nav>
    @if (session('success'))
        <p role="status">{{ session('success') }}</p>
    @endif
    @if (session('error'))
        <p role="alert">{{ session('error') }}</p>
    @endif
    <main>
        <h1>{{ __('Invoice :number', ['number' => $invoice->number()]) }}</h1>
        @if ($invoice->job_card_id)
            <p>{{ __('Linked job:') }} <a href="{{ route('job-cards.show', $invoice->job_card_id) }}">#{{ $invoice->job_card_id }}</a></p>
        @endif
        @if ($invoice->isVoided())
            <p class="void-stamp">{{ __('VOIDED') }}</p>
            <p>{{ __('Reason:') }} {{ $invoice->void_reason }}</p>
        @endif
        <p><strong>{{ __('From:') }}</strong> {{ $invoice->workshop_name }}<br>
            <strong>{{ __('Date:') }}</strong> {{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }}
            @if ($invoice->due_date)
                <br><strong>{{ __('Payment due:') }}</strong> {{ $invoice->due_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
            @endif
        </p>
        <p><strong>{{ __('Bill to:') }}</strong> {{ $invoice->customer_name }}
            @if ($invoice->customer_address)
                <br><span class="address">{{ $invoice->customer_address }}</span>
            @endif
            @if ($invoice->vehicle_plate)
                <br><strong>{{ __('Vehicle:') }}</strong> {{ $invoice->vehicle_plate }}
            @endif
        </p>
        <table>
            <thead>
                <tr>
                    <th scope="col">{{ __('Description') }}</th>
                    <th scope="col" class="amount">{{ __('Quantity') }}</th>
                    <th scope="col" class="amount">{{ __('Unit price') }}</th>
                    <th scope="col" class="amount">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="description">
                            {{ $item['description'] }}
                            @if (! empty($item['inventory_sku']))
                                <span class="d-block text-muted">{{ __('Inventory:') }} {{ $item['inventory_sku'] }}</span>
                            @endif
                        </td>
                        <td class="amount">{{ $item['quantity'] }}</td>
                        <td class="amount">{{ \App\Models\Invoice::formatAmount($item['unit_price_sen']) }}</td>
                        <td class="amount">{{ \App\Models\Invoice::formatAmount($item['total_sen']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @include('invoices.pricing-rows', ['labelColumns' => 3])
                <tr>
                    <th scope="row" colspan="3" class="amount">{{ __('Total') }}</th>
                    <td class="amount"><strong>{{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}</strong></td>
                </tr>
            </tfoot>
        </table>
        @php
            $paid = $invoice->paid_sen ?? 0;
            $refunded = $invoice->refunded_sen ?? 0;
            $credited = $invoice->credited_sen ?? 0;
            $balance = $invoice->balanceDue($paid, $refunded, $credited);
            $customerCredit = $invoice->customerCredit($paid, $refunded, $credited);
        @endphp
        @if ($balance > 0 && ! $invoice->isVoided() && $paymentQrs->isNotEmpty())
            <section aria-labelledby="invoice-payment-qrs">
                <h2 id="invoice-payment-qrs">{{ __('Pay by bank or e-wallet QR') }}</h2>
                <p>{{ __('Use your banking or e-wallet app to pay, then send the transfer proof to the workshop.') }}</p>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem">
                    @foreach ($paymentQrs as $qr)
                        <div style="max-width: 12rem">
                            <strong>{{ $qr->label }}</strong>
                            <img src="{{ route('payment-qrs.image', $qr) }}" alt="{{ __(':label payment QR', ['label' => $qr->label]) }}"
                                style="display: block; max-width: 100%; max-height: 12rem">
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        <p>{{ __('Beta test document — not submitted to MyInvois.') }}</p>
        <section id="invoice-email" aria-labelledby="invoice-email-heading">
            <h2 id="invoice-email-heading">{{ __('Invoice email delivery') }}</h2>
            @if (! $invoice->isVoided())
                @if (filter_var($invoice->customer?->email, FILTER_VALIDATE_EMAIL))
                    <p>{{ __('Send this itemized invoice to :email.', ['email' => $invoice->customer->email]) }}</p>
                    <form action="{{ route('invoices.email-deliveries.store', $invoice) }}" method="POST">
                        @csrf
                        <button type="submit">{{ __('Send invoice email') }}</button>
                    </form>
                @else
                    <p>{{ __('Add a valid email address to the customer record before sending this invoice.') }}</p>
                @endif
            @else
                <p>{{ __('Voided invoices cannot be emailed.') }}</p>
            @endif
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Attempted (MYT)') }}</th>
                        <th scope="col">{{ __('Recipient') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Sent by') }}</th>
                        <th scope="col">{{ __('Retry') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($emailDeliveries as $delivery)
                        <tr>
                            <td>{{ $delivery->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $delivery->recipient_email }}</td>
                            <td>
                                @if ($delivery->status === \App\Models\InvoiceEmailDelivery::STATUS_SENT)
                                    {{ __('Accepted by mail transport') }}
                                @elseif ($delivery->status === \App\Models\InvoiceEmailDelivery::STATUS_FAILED)
                                    {{ __('Failed: :reason', ['reason' => $delivery->failure_message]) }}
                                @else
                                    {{ __('Sending') }}
                                @endif
                            </td>
                            <td>{{ __('User #:id', ['id' => $delivery->sent_by_user_id]) }}</td>
                            <td>
                                @if ($delivery->status === \App\Models\InvoiceEmailDelivery::STATUS_FAILED && ! $invoice->isVoided())
                                    <form action="{{ route('invoices.email-deliveries.retry', [$invoice, $delivery]) }}" method="POST">
                                        @csrf
                                        <button type="submit">{{ __('Retry this email') }}</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('No invoice email attempts yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $emailDeliveries->links() }}
            <p>{{ __('Transport acceptance does not confirm inbox delivery. Failed attempts can be retried; every attempt is recorded.') }}</p>
        </section>
        <section id="payment-reminders" aria-labelledby="payment-reminders-heading">
            <h2 id="payment-reminders-heading">{{ __('Payment reminder history') }}</h2>
            <p>{{ __('Accepted means the mail transport accepted the message, not that it reached the inbox. If an attempt remains sending, its outcome is unknown; check the mail provider before taking further action.') }}</p>
            @forelse ($paymentReminders as $reminder)
                <h3>
                    {{ $reminder->days_overdue === 0 ? __('Due date') : __(':count days overdue', ['count' => $reminder->days_overdue]) }}
                    ({{ __('due :date', ['date' => $reminder->due_date->locale(app()->getLocale())->translatedFormat('d M Y')]) }})
                </h3>
                <table>
                    <thead><tr><th scope="col">{{ __('Attempted (MYT)') }}</th><th scope="col">{{ __('Recipient') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Requested by') }}</th></tr></thead>
                    <tbody>
                        @foreach ($reminder->attempts as $attempt)
                            <tr>
                                <td>{{ $attempt->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $attempt->recipient_email ?? '—' }}</td>
                                <td>
                                    @if ($attempt->status === \App\Models\InvoicePaymentReminderAttempt::SENT)
                                        {{ __('Accepted by mail transport') }}
                                    @elseif ($attempt->status === \App\Models\InvoicePaymentReminderAttempt::FAILED)
                                        {{ __('Failed: :reason', ['reason' => $attempt->failure_message]) }}
                                    @elseif ($attempt->status === \App\Models\InvoicePaymentReminderAttempt::CANCELLED)
                                        {{ __('Skipped: :reason', ['reason' => $attempt->failure_message]) }}
                                    @else
                                        {{ __('Sending / outcome unknown') }}
                                    @endif
                                </td>
                                <td>{{ $attempt->requested_by_user_id ? __('User #:id', ['id' => $attempt->requested_by_user_id]) : __('Scheduled') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($reminder->attempts->last()?->status === \App\Models\InvoicePaymentReminderAttempt::FAILED)
                    <form action="{{ route('invoices.payment-reminders.retry', [$invoice, $reminder]) }}" method="POST">
                        @csrf
                        <button type="submit">{{ __('Retry failed reminder') }}</button>
                    </form>
                @endif
            @empty
                <p>{{ __('No payment reminder attempts yet.') }}</p>
            @endforelse
            {{ $paymentReminders->links() }}
        </section>
        <section id="payments" aria-labelledby="payments-heading">
            <h2 id="payments-heading">{{ __('Payments') }}</h2>
            <p><strong>{{ __($invoice->paymentStatus($paid, $refunded, $credited)) }}</strong>
                @if ($invoice->isOverdue($paid, $today, $refunded, $credited))
                    &middot; <strong>{{ __('Overdue') }}</strong>
                @endif
                <br>
                {{ __('Payments received:') }} {{ \App\Models\Invoice::formatAmount($paid) }}<br>
                {{ __('Refunds:') }} {{ \App\Models\Invoice::formatAmount($refunded) }}<br>
                {{ __('Credits:') }} {{ \App\Models\Invoice::formatAmount($credited) }}<br>
                {{ __('Outstanding:') }} {{ \App\Models\Invoice::formatAmount($balance) }}<br>
                {{ __('Customer credit:') }} {{ \App\Models\Invoice::formatAmount($customerCredit) }}</p>
            @if ($errors->any())
                <ul role="alert">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($balance > 0)
                <form action="{{ route('invoices.payments.store', $invoice) }}" method="POST">
                    @csrf
                    <p>{{ __('Use this for other payments already received outside this app. For QR transfers, use the proof form below. Double-check the amount before saving; entries cannot be edited or deleted here.') }}</p>
                    <p><label for="amount">{{ __('Amount received (RM)') }}</label><br>
                        <input type="text" name="amount" id="amount" inputmode="decimal" pattern="[0-9]{1,11}(\.[0-9]{1,2})?" maxlength="14"
                            value="{{ is_scalar(old('amount')) ? old('amount') : '' }}" required></p>
                    <p><label for="reference">{{ __('Reference (optional)') }}</label><br>
                        <input type="text" name="reference" id="reference" maxlength="255" value="{{ is_string(old('reference')) ? old('reference') : '' }}"></p>
                    <p><label for="payment-method">{{ __('Method (optional)') }}</label><br>
                        <select id="payment-method" name="payment_method"><option value="">{{ __('Not specified') }}</option><option value="cash">{{ __('Cash') }}</option><option value="pos">{{ __('POS') }}</option><option value="transfer">{{ __('Transfer') }}</option></select></p>
                    <button type="submit">{{ __('Record payment') }}</button>
                </form>
            @endif
            @if ($balance > 0)
                <h3>{{ __('Record QR payment with proof') }}</h3>
                @if ($paymentQrs->isEmpty())
                    <p><a href="{{ route('payment-qrs.index') }}">{{ __('Add a workshop QR code') }}</a> {{ __('before recording a QR payment with proof.') }}</p>
                @else
                    <p>{{ __('Use this after receiving a QR payment. The payment is recorded immediately with the uploaded proof.') }}</p>
                    <form action="{{ route('invoices.payment-submissions.store', $invoice) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <p>
                            <label for="admin-qr">{{ __('Payment method') }}</label><br>
                            <select id="admin-qr" name="workshop_payment_qr_id" required>
                                <option value="">{{ __('Select a QR method') }}</option>
                                @foreach ($paymentQrs as $qr)
                                    <option value="{{ $qr->id }}">{{ $qr->label }}</option>
                                @endforeach
                            </select>
                        </p>
                        <p><label for="admin-amount">{{ __('Amount received (RM)') }}</label><br>
                            <input type="text" name="amount" id="admin-amount" inputmode="decimal" pattern="[0-9]{1,13}(\.[0-9]{1,2})?" maxlength="16" required></p>
                        <p><label for="admin-reference">{{ __('Transfer reference (optional)') }}</label><br>
                            <input type="text" name="reference" id="admin-reference" maxlength="255"></p>
                        <p><label for="admin-proof">{{ __('Payment proof (JPG, PNG, WebP or PDF; max 5 MB)') }}</label><br>
                            <input type="file" name="proof" id="admin-proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></p>
                        <button type="submit">{{ __('Record QR payment with proof') }}</button>
                    </form>
                @endif
            @endif
            @if ($canEdit)
                <form action="{{ route('invoices.void', $invoice) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <p><label for="void-reason">{{ __('Reason for voiding') }}</label><br>
                        <textarea id="void-reason" name="reason" maxlength="1000" rows="2" required>{{ old('reason') }}</textarea></p>
                    <button type="submit">{{ __('Void unpaid invoice') }}</button>
                </form>
            @endif
            <h3>{{ __('Payment history') }}</h3>
            <table>
                <thead>
                    <tr><th scope="col">{{ __('Recorded (MYT)') }}</th><th scope="col">{{ __('Method / reference') }}</th><th scope="col">{{ __('Recorded by') }}</th><th scope="col" class="amount">{{ __('Amount') }}</th><th scope="col" class="amount">{{ __('Refunded') }}</th><th scope="col">{{ __('Receipt') }}</th><th scope="col">{{ __('Refund') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php
                            $remainingRefundable = $payment->amount_sen - ($payment->refunded_sen ?? 0);
                            if ($credited > 0) {
                                $remainingRefundable = min($remainingRefundable, $customerCredit);
                            }
                        @endphp
                        <tr>
                            <td>{{ $payment->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                            <td class="description">{{ $payment->payment_method ? ($payment->payment_method === 'pos' ? __('POS') : __(ucfirst($payment->payment_method))) : '—' }} / {{ $payment->reference ?? '—' }}</td>
                            <td>{{ __('User #:id', ['id' => $payment->recorded_by_user_id]) }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($payment->amount_sen) }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($payment->refunded_sen ?? 0) }}</td>
                            <td><a href="{{ route('invoices.payments.receipt', [$invoice, $payment]) }}">{{ __('View receipt') }}</a></td>
                            <td>
                                @if ($canRefund && $remainingRefundable > 0)
                                    <form action="{{ route('invoices.payments.refunds.store', [$invoice, $payment]) }}" method="POST">
                                        @csrf
                                        <span>{{ __('Up to :amount', ['amount' => \App\Models\Invoice::formatAmount($remainingRefundable)]) }}</span>
                                        <label for="refund-amount-{{ $payment->id }}">{{ __('Amount (RM)') }}</label>
                                        <input id="refund-amount-{{ $payment->id }}" name="amount" type="text" inputmode="decimal"
                                            pattern="[0-9]{1,13}(\.[0-9]{1,2})?" maxlength="16" required>
                                        <label for="refund-reason-{{ $payment->id }}">{{ __('Reason') }}</label>
                                        <input id="refund-reason-{{ $payment->id }}" name="reason" maxlength="1000" required>
                                        <label for="refund-reference-{{ $payment->id }}">{{ __('External reference (optional)') }}</label>
                                        <input id="refund-reference-{{ $payment->id }}" name="reference" maxlength="255">
                                        <button type="submit">{{ __('Record external refund') }}</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('No payments recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $payments->links() }}

            <h3>{{ __('Credit notes') }}</h3>
            @if ($canIssueCreditNote)
                <p>{{ __('Remaining amount available for credit: :amount. A credit note reduces the invoice amount; any resulting customer credit is shown above and is not automatically refunded.', ['amount' => \App\Models\Invoice::formatAmount($invoice->total_sen - $credited)]) }}</p>
                <form action="{{ route('invoices.credit-notes.store', $invoice) }}" method="POST">
                    @csrf
                    <label for="credit-amount">{{ __('Credit amount (RM)') }}</label>
                    <input id="credit-amount" name="amount" type="text" inputmode="decimal"
                        pattern="[0-9]{1,13}(\.[0-9]{1,2})?" maxlength="16" required>
                    <label for="credit-reason">{{ __('Reason') }}</label>
                    <input id="credit-reason" name="reason" maxlength="1000" required>
                    <button type="submit">{{ __('Issue credit note') }}</button>
                </form>
            @endif
            <table>
                <thead><tr><th scope="col">{{ __('Credit note') }}</th><th scope="col">{{ __('Issued (MYT)') }}</th><th scope="col">{{ __('Reason') }}</th><th scope="col" class="amount">{{ __('Amount') }}</th><th scope="col">{{ __('Document') }}</th></tr></thead>
                <tbody>
                    @forelse ($creditNotes as $creditNote)
                        <tr>
                            <td>{{ $creditNote->number() }}</td>
                            <td>{{ $creditNote->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $creditNote->reason }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($creditNote->amount_sen) }}</td>
                            <td><a href="{{ route('invoices.credit-notes.show', [$invoice, $creditNote]) }}">{{ __('Print / view') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('No credit notes issued.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $creditNotes->links() }}

            <h3>{{ __('Refund history') }}</h3>
            <table>
                <thead><tr><th scope="col">{{ __('Refund') }}</th><th scope="col">{{ __('Payment') }}</th><th scope="col">{{ __('Recorded (MYT)') }}</th><th scope="col">{{ __('Reason') }}</th><th scope="col" class="amount">{{ __('Amount') }}</th><th scope="col">{{ __('Document') }}</th></tr></thead>
                <tbody>
                    @forelse ($refunds as $refund)
                        <tr>
                            <td>{{ $refund->number() }}</td>
                            <td>PAY-{{ sprintf('%06d', $refund->payment->id) }}</td>
                            <td>{{ $refund->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $refund->reason }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($refund->amount_sen) }}</td>
                            <td><a href="{{ route('invoices.refunds.show', [$invoice, $refund]) }}">{{ __('Print / view') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ __('No refunds recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $refunds->links() }}

            <h3>{{ __('Payment proof submissions') }}</h3>
            <table>
                <thead>
                    <tr><th scope="col">{{ __('Submitted (MYT)') }}</th><th scope="col">{{ __('Method') }}</th><th scope="col">{{ __('Amount') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Proof') }}</th><th scope="col">{{ __('Review') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($paymentSubmissions as $submission)
                        <tr>
                            <td>{{ $submission->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $submission->payment_method }}</td>
                            <td>{{ \App\Models\Invoice::formatAmount($submission->amount_sen) }}</td>
                            <td>{{ __(ucfirst($submission->status)) }}</td>
                            <td><a href="{{ route('invoices.payment-submissions.proof', [$invoice, $submission]) }}">{{ __('Download') }}</a></td>
                            <td>
                                @if ($submission->review_note)
                                    {{ $submission->review_note }}
                                @elseif ($submission->status === \App\Models\InvoicePaymentSubmission::STATUS_PENDING)
                                    <form action="{{ route('invoices.payment-submissions.approve', [$invoice, $submission]) }}" method="POST">
                                        @csrf
                                        <button type="submit">{{ __('Approve and record') }}</button>
                                    </form>
                                    <form action="{{ route('invoices.payment-submissions.reject', [$invoice, $submission]) }}" method="POST">
                                        @csrf
                                        <label for="reject-reason-{{ $submission->id }}">{{ __('Reason to reject') }}</label>
                                        <input id="reject-reason-{{ $submission->id }}" name="reason" maxlength="1000" required>
                                        <button type="submit">{{ __('Reject') }}</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ __('No payment proofs submitted.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $paymentSubmissions->links() }}
        </section>
    </main>
</body>
</html>
