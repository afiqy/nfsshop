<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number() }}</title>
</head>
<body>
    <h1>Invoice {{ $invoice->number() }}</h1>
    <p>Hello {{ $invoice->customer_name }},</p>
    <p>{{ $invoice->workshop_name }} has sent you this invoice.</p>
    <p>
        <strong>Invoice date:</strong> {{ $invoice->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }}
        @if ($invoice->due_date)
            <br><strong>Payment due:</strong> {{ $invoice->due_date->format('d M Y') }}
        @endif
    </p>
    @if ($invoice->customer_address)
        <p><strong>Bill to:</strong><br>{!! nl2br(e($invoice->customer_address)) !!}</p>
    @endif
    @if ($invoice->vehicle_plate)
        <p><strong>Vehicle:</strong> {{ $invoice->vehicle_plate }}</p>
    @endif
    <table>
        <thead>
            <tr>
                <th scope="col">Description</th>
                <th scope="col">Quantity</th>
                <th scope="col">Unit price</th>
                <th scope="col">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item['description'] }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>{{ \App\Models\Invoice::formatAmount($item['unit_price_sen']) }}</td>
                    <td>{{ \App\Models\Invoice::formatAmount($item['total_sen']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th scope="row" colspan="3">Invoice total</th>
                <td>{{ \App\Models\Invoice::formatAmount($invoice->total_sen) }}</td>
            </tr>
        </tfoot>
    </table>
    @php
        $paid = $invoice->paid_sen ?? 0;
        $refunded = $invoice->refunded_sen ?? 0;
        $credited = $invoice->credited_sen ?? 0;
    @endphp
    <p>
        <strong>Status:</strong> {{ $invoice->paymentStatus($paid, $refunded, $credited) }}<br>
        <strong>Outstanding:</strong> {{ \App\Models\Invoice::formatAmount($invoice->balanceDue($paid, $refunded, $credited)) }}<br>
        <strong>Customer credit:</strong> {{ \App\Models\Invoice::formatAmount($invoice->customerCredit($paid, $refunded, $credited)) }}
    </p>
    <p>This beta invoice email is not submitted to or validated by LHDN MyInvois.</p>
</body>
</html>
