<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Payment reminder</title></head>
<body>
    <h1>Payment reminder: {{ $invoice->number() }}</h1>
    <p>Hello {{ $invoice->customer_name }},</p>
    <p>{{ $invoice->workshop_name }} would like to remind you about the outstanding balance on this invoice.</p>
    <p>
        <strong>Invoice:</strong> {{ $invoice->number() }}<br>
        <strong>Due date:</strong> {{ $invoice->due_date->format('d M Y') }}<br>
        <strong>Outstanding balance:</strong> {{ \App\Models\Invoice::formatAmount($balanceSen) }}
    </p>
    <p>If you have already paid, please contact the workshop with your payment proof.</p>
    <p>This reminder is not a MyInvois-validated invoice.</p>
</body>
</html>
