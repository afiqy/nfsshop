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
            nav, [role="status"] { display: none; }
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
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </nav>
    @if (session('success'))
        <p role="status">{{ session('success') }}</p>
    @endif
    <main>
        <h1>Invoice {{ $invoice->number() }}</h1>
        <p><strong>From:</strong> {{ $invoice->workshop_name }}<br>
            <strong>Date:</strong> {{ $invoice->created_at->format('d M Y') }}</p>
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
                        <td class="description">{{ $item['description'] }}</td>
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
    </main>
</body>
</html>
