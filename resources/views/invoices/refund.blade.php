<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Refund {{ $refund->number() }}</title>
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
        <a href="{{ route('invoices.show', $invoice) }}#payments">Back to invoice</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </nav>
    <main>
        @if (session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        <h1>Refund acknowledgment {{ $refund->number() }}</h1>
        <dl>
            <dt>Workshop</dt>
            <dd>{{ $invoice->workshop_name }}</dd>
            <dt>Customer</dt>
            <dd>{{ $invoice->customer_name }}</dd>
            <dt>Original invoice</dt>
            <dd>{{ $invoice->number() }}</dd>
            <dt>Original payment</dt>
            <dd>PAY-{{ sprintf('%06d', $refund->payment->id) }}</dd>
            <dt>Refund recorded (MYT)</dt>
            <dd>{{ $refund->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</dd>
            <dt>Refund amount</dt>
            <dd>{{ \App\Models\Invoice::formatAmount($refund->amount_sen) }}</dd>
            <dt>External transfer reference</dt>
            <dd>{{ $refund->reference ?? '—' }}</dd>
            <dt>Reason</dt>
            <dd>{{ $refund->reason }}</dd>
        </dl>
        <p>This acknowledges a refund recorded after money was returned outside this app. The app does not transfer funds. This is not a MyInvois-validated document.</p>
    </main>
</body>
</html>
