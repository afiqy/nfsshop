@if ($invoice->pricing)
    <tr><th scope="row" colspan="{{ $labelColumns }}">Subtotal</th><td>{{ \App\Models\Invoice::formatAmount($invoice->pricing['subtotal_sen']) }}</td></tr>
    <tr><th scope="row" colspan="{{ $labelColumns }}">Discount ({{ number_format($invoice->pricing['discount_rate'] / 100, 2) }}%)</th><td>-{{ \App\Models\Invoice::formatAmount($invoice->pricing['discount_sen']) }}</td></tr>
    <tr><th scope="row" colspan="{{ $labelColumns }}">Tax {{ $invoice->pricing['tax_mode'] === 'inclusive' ? 'included' : 'added' }} ({{ number_format($invoice->pricing['tax_rate'] / 100, 2) }}%)</th><td>{{ \App\Models\Invoice::formatAmount($invoice->pricing['tax_sen']) }}</td></tr>
@endif
