<?php

namespace App\Services;

use App\Models\Invoice;
use Brick\Math\BigInteger;
use UnexpectedValueException;

class ServiceRevenueReport
{
    public function calculate(string $start, string $end): array
    {
        $services = [];
        $unclassified = ['invoiced_sen' => 0, 'credited_sen' => 0];
        $invoices = Invoice::query()->where('status', Invoice::STATUS_ISSUED)
            ->where(function ($query) use ($start, $end) {
                $query->where(fn ($issued) => $issued->where('created_at', '>=', $start)->where('created_at', '<', $end))
                    ->orWhereHas('creditNotes', fn ($credits) => $credits
                        ->where('created_at', '>=', $start)->where('created_at', '<', $end));
            })
            ->with(['creditNotes' => fn ($credits) => $credits->where('created_at', '<', $end)->orderBy('created_at')->orderBy('id')])
            ->select(['id', 'items', 'pricing', 'total_sen', 'created_at'])->lazyById(100);

        foreach ($invoices as $invoice) {
            $lines = $this->invoiceLines($invoice);
            $created = $invoice->created_at->format('Y-m-d H:i:s');
            $inPeriod = $created >= $start && $created < $end;
            foreach ($lines as $line) {
                if ($inPeriod) {
                    $this->add($services, $unclassified, $line, 'invoiced_sen', $line['net']);
                }
            }
            foreach ($invoice->creditNotes as $credit) {
                if ($credit->created_at->lt($invoice->created_at)) {
                    throw new UnexpectedValueException("Invoice #{$invoice->id} has a credit note dated before the invoice.");
                }
                $amount = $credit->amount_sen;
                $gross = array_map(fn ($line) => $line['net'] + $line['tax'], $lines);
                if ($amount <= 0 || $amount > array_sum($gross)) {
                    throw new UnexpectedValueException("Invoice #{$invoice->id} has invalid credit note amounts.");
                }
                $shares = $this->allocate($amount, $gross);
                foreach ($lines as $index => &$line) {
                    [$netCredit, $taxCredit] = $this->allocate($shares[$index], [$line['net'], $line['tax']]);
                    $line['net'] -= $netCredit;
                    $line['tax'] -= $taxCredit;
                    if ($credit->created_at->format('Y-m-d H:i:s') >= $start) {
                        $this->add($services, $unclassified, $line, 'credited_sen', $netCredit);
                    }
                }
                unset($line);
            }
        }

        return ['services' => collect($services)->sortByDesc(fn ($row) => $row['invoiced_sen'] - $row['credited_sen'])->values(),
            'unclassified' => $unclassified];
    }

    private function invoiceLines(Invoice $invoice): array
    {
        $items = $invoice->items;
        if (! is_array($items)) {
            throw new UnexpectedValueException("Invoice #{$invoice->id} has invalid line items.");
        }
        $amounts = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['total_sen']) || ! is_int($item['total_sen']) || $item['total_sen'] < 0) {
                throw new UnexpectedValueException("Invoice #{$invoice->id} has invalid line amounts.");
            }
            $amounts[] = $item['total_sen'];
        }
        $subtotal = array_sum($amounts);
        $pricing = $invoice->pricing;
        if ($pricing === null) {
            $discount = $tax = 0;
            $mode = 'exclusive';
        } elseif (is_array($pricing) && isset($pricing['subtotal_sen'], $pricing['discount_sen'], $pricing['discount_rate'], $pricing['tax_sen'], $pricing['tax_rate'], $pricing['tax_mode'])
            && is_int($pricing['subtotal_sen']) && is_int($pricing['discount_sen']) && is_int($pricing['tax_sen'])
            && is_int($pricing['discount_rate']) && $pricing['discount_rate'] >= 0 && $pricing['discount_rate'] <= 10000
            && is_int($pricing['tax_rate']) && $pricing['tax_rate'] >= 0 && $pricing['tax_rate'] <= 10000
            && $pricing['subtotal_sen'] === $subtotal && in_array($pricing['tax_mode'], ['inclusive', 'exclusive'], true)) {
            $discount = $pricing['discount_sen'];
            $tax = $pricing['tax_sen'];
            $mode = $pricing['tax_mode'];
        } else {
            throw new UnexpectedValueException("Invoice #{$invoice->id} has incomplete or invalid pricing data.");
        }
        if (! is_int($subtotal) || $discount < 0 || $discount > $subtotal || $tax < 0
            || $tax > $subtotal - $discount
            || $invoice->total_sen !== $subtotal - $discount + ($mode === 'exclusive' ? $tax : 0)) {
            throw new UnexpectedValueException("Invoice #{$invoice->id} has inconsistent invoice totals.");
        }
        if ($pricing !== null) {
            $expectedDiscount = $this->roundRatio($subtotal, $pricing['discount_rate'], 10000);
            $taxDivisor = $mode === 'inclusive' ? 10000 + $pricing['tax_rate'] : 10000;
            $expectedTax = $this->roundRatio($subtotal - $discount, $pricing['tax_rate'], $taxDivisor);
            if ($discount !== $expectedDiscount || $tax !== $expectedTax) {
                throw new UnexpectedValueException("Invoice #{$invoice->id} has inconsistent pricing amounts.");
            }
        }

        $discounts = $this->allocate($discount, $amounts);
        $discounted = array_map(fn ($amount, $share) => $amount - $share, $amounts, $discounts);
        $taxes = $this->allocate($tax, $discounted);
        $lines = [];
        foreach (array_values($items) as $index => $item) {
            $lines[] = [
                'id' => isset($item['service_id'], $item['service_name']) ? (int) $item['service_id'] : null,
                'name' => $item['service_name'] ?? null,
                'net' => $discounted[$index] - ($mode === 'inclusive' ? $taxes[$index] : 0),
                'tax' => $taxes[$index],
            ];
        }

        return $lines;
    }

    private function add(array &$services, array &$unclassified, array $line, string $field, int $amount): void
    {
        if ($line['id'] === null) {
            $unclassified[$field] += $amount;

            return;
        }
        $id = $line['id'];
        $services[$id] ??= ['id' => $id, 'name' => $line['name'], 'invoiced_sen' => 0, 'credited_sen' => 0];
        $services[$id][$field] += $amount;
    }

    private function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        if ($amount === 0) {
            return array_fill(0, count($weights), 0);
        }
        $shares = [];
        $remainders = [];
        foreach ($weights as $index => $weight) {
            [$quotient, $remainder] = BigInteger::of($amount)->multipliedBy($weight)->quotientAndRemainder($total);
            $shares[$index] = $quotient->toInt();
            $remainders[] = ['index' => $index, 'remainder' => $remainder->toInt()];
        }
        usort($remainders, fn ($a, $b) => ($b['remainder'] <=> $a['remainder']) ?: ($a['index'] <=> $b['index']));
        for ($i = 0, $remaining = $amount - array_sum($shares); $i < $remaining; $i++) {
            $shares[$remainders[$i]['index']]++;
        }

        return $shares;
    }

    private function roundRatio(int $amount, int $rate, int $divisor): int
    {
        return BigInteger::of($amount)->multipliedBy($rate)->plus(intdiv($divisor, 2))
            ->quotient($divisor)->toInt();
    }
}
