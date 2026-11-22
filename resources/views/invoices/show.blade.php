<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->number() }}</title>
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
        <a href="{{ route('invoices.index') }}">Back to invoices</a>
        @if ($canEdit)
            <a href="{{ route('invoices.edit', $invoice) }}">Edit invoice</a>
        @endif
        <a href="{{ route('myinvois.buyer.edit', $invoice) }}">MyInvois preparation</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </nav>
    @if (session('success'))
        <p role="status">{{ session('success') }}</p>
    @endif
    @if (session('error'))
        <p role="alert">{{ session('error') }}</p>
    @endif
    <main>
        <h1>Invoice {{ $invoice->number() }}</h1>
        @if ($invoice->isVoided())
            <p class="void-stamp">VOIDED</p>
            <p>Reason: {{ $invoice->void_reason }}</p>
        @endif
        <p><strong>From:</strong> {{ $invoice->workshop_name }}<br>
            <strong>Date:</strong> {{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }}
            @if ($invoice->due_date)
                <br><strong>Payment due:</strong> {{ $invoice->due_date->format('d M Y') }}
            @endif
        </p>
        <p><strong>Bill to:</strong> {{ $invoice->customer_name }}
            @if ($invoice->customer_address)
                <br><span class="address">{{ $invoice->customer_address }}</span>
            @endif
            @if ($invoice->vehicle_plate)
                <br><strong>Vehicle:</strong> {{ $invoice->vehicle_plate }}
            @endif
        </p>
        <table>
            <thead>
                <tr>
                    <th scope="col">Description</th>
                    <th scope="col" class="amount">Quantity</th>
                    <th scope="col" class="amount">Unit price</th>
                    <th scope="col" class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="description">
                            {{ $item['description'] }}
                            @if (! empty($item['inventory_sku']))
                                <span class="d-block text-muted">Inventory: {{ $item['inventory_sku'] }}</span>
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
                    <th scope="row" colspan="3" class="amount">Total</th>
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
                <h2 id="invoice-payment-qrs">Pay by bank or e-wallet QR</h2>
                <p>Use your banking or e-wallet app to pay, then send the transfer proof to the workshop.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem">
                    @foreach ($paymentQrs as $qr)
                        <div style="max-width: 12rem">
                            <strong>{{ $qr->label }}</strong>
                            <img src="{{ route('payment-qrs.image', $qr) }}" alt="{{ $qr->label }} payment QR"
                                style="display: block; max-width: 100%; max-height: 12rem">
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        <p>Beta test document — not submitted to MyInvois.</p>
        <section id="invoice-email" aria-labelledby="invoice-email-heading">
            <h2 id="invoice-email-heading">Invoice email delivery</h2>
            @if (! $invoice->isVoided())
                @if (filter_var($invoice->customer?->email, FILTER_VALIDATE_EMAIL))
                    <p>Send this itemized invoice to {{ $invoice->customer->email }}.</p>
                    <form action="{{ route('invoices.email-deliveries.store', $invoice) }}" method="POST">
                        @csrf
                        <button type="submit">Send invoice email</button>
                    </form>
                @else
                    <p>Add a valid email address to the customer record before sending this invoice.</p>
                @endif
            @else
                <p>Voided invoices cannot be emailed.</p>
            @endif
            <table>
                <thead>
                    <tr>
                        <th scope="col">Attempted (MYT)</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Status</th>
                        <th scope="col">Sent by</th>
                        <th scope="col">Retry</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($emailDeliveries as $delivery)
                        <tr>
                            <td>{{ $delivery->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                            <td>{{ $delivery->recipient_email }}</td>
                            <td>
                                @if ($delivery->status === \App\Models\InvoiceEmailDelivery::STATUS_SENT)
                                    Accepted by mail transport
                                @elseif ($delivery->status === \App\Models\InvoiceEmailDelivery::STATUS_FAILED)
                                    Failed: {{ $delivery->failure_message }}
                                @else
                                    Sending
                                @endif
                            </td>
                            <td>User #{{ $delivery->sent_by_user_id }}</td>
                            <td>
                                @if ($delivery->status === \App\Models\InvoiceEmailDelivery::STATUS_FAILED && ! $invoice->isVoided())
                                    <form action="{{ route('invoices.email-deliveries.retry', [$invoice, $delivery]) }}" method="POST">
                                        @csrf
                                        <button type="submit">Retry this email</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No invoice email attempts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $emailDeliveries->links() }}
            <p>Transport acceptance does not confirm inbox delivery. Failed attempts can be retried; every attempt is recorded.</p>
        </section>
        <section id="payment-reminders" aria-labelledby="payment-reminders-heading">
            <h2 id="payment-reminders-heading">Payment reminder history</h2>
            <p>Accepted means the mail transport accepted the message, not that it reached the inbox.
                If an attempt remains sending, its outcome is unknown; check the mail provider before taking further action.</p>
            @forelse ($paymentReminders as $reminder)
                <h3>
                    {{ $reminder->days_overdue === 0 ? 'Due date' : $reminder->days_overdue.' days overdue' }}
                    (due {{ $reminder->due_date->format('d M Y') }})
                </h3>
                <table>
                    <thead><tr><th scope="col">Attempted (MYT)</th><th scope="col">Recipient</th><th scope="col">Status</th><th scope="col">Requested by</th></tr></thead>
                    <tbody>
                        @foreach ($reminder->attempts as $attempt)
                            <tr>
                                <td>{{ $attempt->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                                <td>{{ $attempt->recipient_email ?? '—' }}</td>
                                <td>
                                    @if ($attempt->status === \App\Models\InvoicePaymentReminderAttempt::SENT)
                                        Accepted by mail transport
                                    @elseif ($attempt->status === \App\Models\InvoicePaymentReminderAttempt::FAILED)
                                        Failed: {{ $attempt->failure_message }}
                                    @else
                                        Sending / outcome unknown
                                    @endif
                                </td>
                                <td>{{ $attempt->requested_by_user_id ? 'User #'.$attempt->requested_by_user_id : 'Scheduled' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($reminder->attempts->last()?->status === \App\Models\InvoicePaymentReminderAttempt::FAILED)
                    <form action="{{ route('invoices.payment-reminders.retry', [$invoice, $reminder]) }}" method="POST">
                        @csrf
                        <button type="submit">Retry failed reminder</button>
                    </form>
                @endif
            @empty
                <p>No payment reminder attempts yet.</p>
            @endforelse
            {{ $paymentReminders->links() }}
        </section>
        <section id="payments" aria-labelledby="payments-heading">
            <h2 id="payments-heading">Payments</h2>
            <p><strong>{{ $invoice->paymentStatus($paid, $refunded, $credited) }}</strong>
                @if ($invoice->isOverdue($paid, $today, $refunded, $credited))
                    &middot; <strong>Overdue</strong>
                @endif
                <br>
                Payments received: {{ \App\Models\Invoice::formatAmount($paid) }}<br>
                Refunds: {{ \App\Models\Invoice::formatAmount($refunded) }}<br>
                Credits: {{ \App\Models\Invoice::formatAmount($credited) }}<br>
                Outstanding: {{ \App\Models\Invoice::formatAmount($balance) }}<br>
                Customer credit: {{ \App\Models\Invoice::formatAmount($customerCredit) }}</p>
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
                    <p>Use this for other payments already received outside this app. For QR transfers, use the proof form below. Double-check the amount before saving; entries cannot be edited or deleted here.</p>
                    <p><label for="amount">Amount received (RM)</label><br>
                        <input type="text" name="amount" id="amount" inputmode="decimal" pattern="[0-9]{1,11}(\.[0-9]{1,2})?" maxlength="14"
                            value="{{ is_scalar(old('amount')) ? old('amount') : '' }}" required></p>
                    <p><label for="reference">Reference (optional)</label><br>
                        <input type="text" name="reference" id="reference" maxlength="255" value="{{ is_string(old('reference')) ? old('reference') : '' }}"></p>
                    <button type="submit">Record payment</button>
                </form>
            @endif
            @if ($balance > 0)
                <h3>Record QR payment with proof</h3>
                @if ($paymentQrs->isEmpty())
                    <p><a href="{{ route('payment-qrs.index') }}">Add a workshop QR code</a> before recording a QR payment with proof.</p>
                @else
                    <p>Use this after receiving a QR payment. The payment is recorded immediately with the uploaded proof.</p>
                    <form action="{{ route('invoices.payment-submissions.store', $invoice) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <p>
                            <label for="admin-qr">Payment method</label><br>
                            <select id="admin-qr" name="workshop_payment_qr_id" required>
                                <option value="">Select a QR method</option>
                                @foreach ($paymentQrs as $qr)
                                    <option value="{{ $qr->id }}">{{ $qr->label }}</option>
                                @endforeach
                            </select>
                        </p>
                        <p><label for="admin-amount">Amount received (RM)</label><br>
                            <input type="text" name="amount" id="admin-amount" inputmode="decimal" pattern="[0-9]{1,13}(\.[0-9]{1,2})?" maxlength="16" required></p>
                        <p><label for="admin-reference">Transfer reference (optional)</label><br>
                            <input type="text" name="reference" id="admin-reference" maxlength="255"></p>
                        <p><label for="admin-proof">Payment proof (JPG, PNG, WebP or PDF; max 5 MB)</label><br>
                            <input type="file" name="proof" id="admin-proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></p>
                        <button type="submit">Record QR payment with proof</button>
                    </form>
                @endif
            @endif
            @if ($canEdit)
                <form action="{{ route('invoices.void', $invoice) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <p><label for="void-reason">Reason for voiding</label><br>
                        <textarea id="void-reason" name="reason" maxlength="1000" rows="2" required>{{ old('reason') }}</textarea></p>
                    <button type="submit">Void unpaid invoice</button>
                </form>
            @endif
            <h3>Payment history</h3>
            <table>
                <thead>
                    <tr><th scope="col">Recorded (MYT)</th><th scope="col">Reference</th><th scope="col">Recorded by</th><th scope="col" class="amount">Amount</th><th scope="col" class="amount">Refunded</th><th scope="col">Receipt</th><th scope="col">Refund</th></tr>
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
                            <td>{{ $payment->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                            <td class="description">{{ $payment->reference ?? '—' }}</td>
                            <td>User #{{ $payment->recorded_by_user_id }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($payment->amount_sen) }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($payment->refunded_sen ?? 0) }}</td>
                            <td><a href="{{ route('invoices.payments.receipt', [$invoice, $payment]) }}">View receipt</a></td>
                            <td>
                                @if ($canRefund && $remainingRefundable > 0)
                                    <form action="{{ route('invoices.payments.refunds.store', [$invoice, $payment]) }}" method="POST">
                                        @csrf
                                        <span>Up to {{ \App\Models\Invoice::formatAmount($remainingRefundable) }}</span>
                                        <label for="refund-amount-{{ $payment->id }}">Amount (RM)</label>
                                        <input id="refund-amount-{{ $payment->id }}" name="amount" type="text" inputmode="decimal"
                                            pattern="[0-9]{1,13}(\.[0-9]{1,2})?" maxlength="16" required>
                                        <label for="refund-reason-{{ $payment->id }}">Reason</label>
                                        <input id="refund-reason-{{ $payment->id }}" name="reason" maxlength="1000" required>
                                        <label for="refund-reference-{{ $payment->id }}">External reference (optional)</label>
                                        <input id="refund-reference-{{ $payment->id }}" name="reference" maxlength="255">
                                        <button type="submit">Record external refund</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No payments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $payments->links() }}

            <h3>Credit notes</h3>
            @if ($canIssueCreditNote)
                <p>Remaining amount available for credit: {{ \App\Models\Invoice::formatAmount($invoice->total_sen - $credited) }}. A credit note reduces the invoice amount; any resulting customer credit is shown above and is not automatically refunded.</p>
                <form action="{{ route('invoices.credit-notes.store', $invoice) }}" method="POST">
                    @csrf
                    <label for="credit-amount">Credit amount (RM)</label>
                    <input id="credit-amount" name="amount" type="text" inputmode="decimal"
                        pattern="[0-9]{1,13}(\.[0-9]{1,2})?" maxlength="16" required>
                    <label for="credit-reason">Reason</label>
                    <input id="credit-reason" name="reason" maxlength="1000" required>
                    <button type="submit">Issue credit note</button>
                </form>
            @endif
            <table>
                <thead><tr><th scope="col">Credit note</th><th scope="col">Issued (MYT)</th><th scope="col">Reason</th><th scope="col" class="amount">Amount</th><th scope="col">Document</th></tr></thead>
                <tbody>
                    @forelse ($creditNotes as $creditNote)
                        <tr>
                            <td>{{ $creditNote->number() }}</td>
                            <td>{{ $creditNote->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                            <td>{{ $creditNote->reason }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($creditNote->amount_sen) }}</td>
                            <td><a href="{{ route('invoices.credit-notes.show', [$invoice, $creditNote]) }}">Print / view</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No credit notes issued.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $creditNotes->links() }}

            <h3>Refund history</h3>
            <table>
                <thead><tr><th scope="col">Refund</th><th scope="col">Payment</th><th scope="col">Recorded (MYT)</th><th scope="col">Reason</th><th scope="col" class="amount">Amount</th><th scope="col">Document</th></tr></thead>
                <tbody>
                    @forelse ($refunds as $refund)
                        <tr>
                            <td>{{ $refund->number() }}</td>
                            <td>PAY-{{ sprintf('%06d', $refund->payment->id) }}</td>
                            <td>{{ $refund->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                            <td>{{ $refund->reason }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($refund->amount_sen) }}</td>
                            <td><a href="{{ route('invoices.refunds.show', [$invoice, $refund]) }}">Print / view</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No refunds recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $refunds->links() }}

            <h3>Payment proof submissions</h3>
            <table>
                <thead>
                    <tr><th scope="col">Submitted (MYT)</th><th scope="col">Method</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Proof</th><th scope="col">Review</th></tr>
                </thead>
                <tbody>
                    @forelse ($paymentSubmissions as $submission)
                        <tr>
                            <td>{{ $submission->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                            <td>{{ $submission->payment_method }}</td>
                            <td>{{ \App\Models\Invoice::formatAmount($submission->amount_sen) }}</td>
                            <td>{{ ucfirst($submission->status) }}</td>
                            <td><a href="{{ route('invoices.payment-submissions.proof', [$invoice, $submission]) }}">Download</a></td>
                            <td>
                                @if ($submission->review_note)
                                    {{ $submission->review_note }}
                                @elseif ($submission->status === \App\Models\InvoicePaymentSubmission::STATUS_PENDING)
                                    <form action="{{ route('invoices.payment-submissions.approve', [$invoice, $submission]) }}" method="POST">
                                        @csrf
                                        <button type="submit">Approve and record</button>
                                    </form>
                                    <form action="{{ route('invoices.payment-submissions.reject', [$invoice, $submission]) }}" method="POST">
                                        @csrf
                                        <label for="reject-reason-{{ $submission->id }}">Reason to reject</label>
                                        <input id="reject-reason-{{ $submission->id }}" name="reason" maxlength="1000" required>
                                        <button type="submit">Reject</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No payment proofs submitted.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $paymentSubmissions->links() }}
        </section>
    </main>
</body>
</html>
