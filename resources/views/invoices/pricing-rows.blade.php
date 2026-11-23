@if ($invoice->pricing)
    <tr><th scope="row" colspan="{{ $labelColumns }}">{{ __('Subtotal') }}</th><td>{{ \App\Models\Invoice::formatAmount($invoice->pricing['subtotal_sen']) }}</td></tr>
    <tr><th scope="row" colspan="{{ $labelColumns }}">{{ __('Discount (:rate%)', ['rate' => number_format($invoice->pricing['discount_rate'] / 100, 2)]) }}</th><td>-{{ \App\Models\Invoice::formatAmount($invoice->pricing['discount_sen']) }}</td></tr>
    <tr><th scope="row" colspan="{{ $labelColumns }}">{{ $invoice->pricing['tax_mode'] === 'inclusive'
        ? __('Tax included (:rate%)', ['rate' => number_format($invoice->pricing['tax_rate'] / 100, 2)])
        : __('Tax added (:rate%)', ['rate' => number_format($invoice->pricing['tax_rate'] / 100, 2)]) }}</th><td>{{ \App\Models\Invoice::formatAmount($invoice->pricing['tax_sen']) }}</td></tr>
@endif
