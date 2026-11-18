<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment acknowledgment {{ sprintf('PAY-%06d', $payment->id) }}</title>
    <style>
        body { margin: 2rem auto; max-width: 42rem; padding: 0 1rem; font: 1rem/1.5 system-ui, sans-serif; color: #242424; }
        nav { display: flex; gap: 1rem; align-items: center; margin-bottom: 2rem; }
        dt { font-weight: bold; }
        dd { margin: 0 0 .75rem; overflow-wrap: anywhere; }
        @media print {
            nav { display: none; }
            body { margin: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <nav>
        <a href="{{ route('invoices.show', $invoice) }}#payments">Back to invoice payments</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </nav>
    <main>
        <h1>Payment acknowledgment</h1>
        <dl>
            <dt>Receipt reference</dt>
            <dd>{{ sprintf('PAY-%06d', $payment->id) }}</dd>
            <dt>Workshop</dt>
            <dd>{{ $invoice->workshop_name }}</dd>
            <dt>Invoice</dt>
            <dd>{{ $invoice->number() }}</dd>
            <dt>Billed to</dt>
            <dd>{{ $invoice->customer_name }}</dd>
            <dt>Payment recorded (MYT)</dt>
            <dd>{{ $payment->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</dd>
            <dt>Amount received for this payment</dt>
            <dd>{{ \App\Models\Invoice::formatAmount($payment->amount_sen) }}</dd>
        </dl>
        <p>This acknowledges one recorded payment only. It does not confirm the invoice is paid in full and is not a MyInvois-validated document.</p>
    </main>
</body>
</html>
