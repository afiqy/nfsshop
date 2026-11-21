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
        @media print {
            nav, [role="status"], #payments { display: none; }
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
        <a href="{{ route('myinvois.buyer.edit', $invoice) }}">MyInvois preparation</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </nav>
    @if (session('success'))
        <p role="status">{{ session('success') }}</p>
    @endif
    <main>
        <h1>Invoice {{ $invoice->number() }}</h1>
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
                <tr>
                    <th scope="row" colspan="3" class="amount">Total</th>
                    <td class="amount"><strong>{{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}</strong></td>
                </tr>
            </tfoot>
        </table>
        <p>Beta test document — not submitted to MyInvois.</p>
        <section id="payments" aria-labelledby="payments-heading">
            @php
                $paid = $invoice->paid_sen ?? 0;
                $balance = $invoice->total_sen - $paid;
            @endphp
            <h2 id="payments-heading">Payments</h2>
            <p><strong>{{ $invoice->paymentStatus($paid) }}</strong>
                @if ($invoice->isOverdue($paid, $today))
                    &middot; <strong>Overdue</strong>
                @endif
                <br>
                Recorded: {{ \App\Models\Invoice::formatAmount($paid) }}<br>
                Outstanding: {{ \App\Models\Invoice::formatAmount($balance) }}</p>
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
                    <p>Only record money already received outside this app. Double-check the amount before saving; entries cannot be edited or deleted here.</p>
                    <p><label for="amount">Amount received (RM)</label><br>
                        <input type="text" name="amount" id="amount" inputmode="decimal" pattern="[0-9]{1,11}(\.[0-9]{1,2})?" maxlength="14"
                            value="{{ is_scalar(old('amount')) ? old('amount') : '' }}" required></p>
                    <p><label for="reference">Reference (optional)</label><br>
                        <input type="text" name="reference" id="reference" maxlength="255" value="{{ is_string(old('reference')) ? old('reference') : '' }}"></p>
                    <button type="submit">Record payment</button>
                </form>
            @endif
            <h3>Payment history</h3>
            <table>
                <thead>
                    <tr><th scope="col">Recorded (MYT)</th><th scope="col">Reference</th><th scope="col">Recorded by</th><th scope="col" class="amount">Amount</th><th scope="col">Receipt</th></tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                            <td class="description">{{ $payment->reference ?? '—' }}</td>
                            <td>User #{{ $payment->recorded_by_user_id }}</td>
                            <td class="amount">{{ \App\Models\Invoice::formatAmount($payment->amount_sen) }}</td>
                            <td><a href="{{ route('invoices.payments.receipt', [$invoice, $payment]) }}">View receipt</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No payments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $payments->links() }}
        </section>
    </main>
</body>
</html>
