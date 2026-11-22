<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceCreditNote;
use App\Models\InvoicePayment;
use App\Models\InvoicePaymentSubmission;
use App\Models\InvoiceRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceAdjustmentService
{
    public function recordRefund(
        Invoice $invoice,
        InvoicePayment $payment,
        User $actor,
        array $data
    ): InvoiceRefund {
        return DB::connection('tenant')->transaction(function () use ($invoice, $payment, $actor, $data) {
            $record = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->assertIssued($record);
            $lockedPayment = $record->payments()->lockForUpdate()->findOrFail($payment->id);
            $amount = $this->amount($data['amount']);
            $refunded = (int) $lockedPayment->refunds()->sum('amount_sen');
            $remainingPayment = $lockedPayment->amount_sen - $refunded;
            $paidTotal = (int) $record->payments()->sum('amount_sen');
            $refundedTotal = (int) $record->refunds()->sum('invoice_refunds.amount_sen');
            $creditedTotal = (int) $record->creditNotes()->sum('amount_sen');
            $customerCredit = $record->customerCredit($paidTotal, $refundedTotal, $creditedTotal);
            $maximumRefund = $creditedTotal > 0
                ? min($remainingPayment, $customerCredit)
                : $remainingPayment;
            if ($amount > $maximumRefund) {
                throw ValidationException::withMessages([
                    'amount' => $creditedTotal > 0
                        ? 'Refunds after a credit note cannot exceed the remaining customer credit balance.'
                        : 'The refund exceeds the unrefunded amount of this payment.',
                ]);
            }

            return $lockedPayment->refunds()->create([
                'amount_sen' => $amount,
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
                'refunded_by_user_id' => $actor->id,
            ]);
        }, 3);
    }

    public function issueCreditNote(Invoice $invoice, User $actor, array $data): InvoiceCreditNote
    {
        return DB::connection('tenant')->transaction(function () use ($invoice, $actor, $data) {
            $record = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->assertIssued($record);
            if (! $record->payments()->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'A credit note can only be issued for an invoice with a recorded payment.',
                ]);
            }
            if ($record->paymentSubmissions()->where('status', InvoicePaymentSubmission::STATUS_PENDING)->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'Resolve pending payment proofs before issuing a credit note.',
                ]);
            }

            $amount = $this->amount($data['amount']);
            $credited = (int) $record->creditNotes()->sum('amount_sen');
            if ($amount > $record->total_sen - $credited) {
                throw ValidationException::withMessages([
                    'amount' => 'The credit note exceeds the remaining invoice amount.',
                ]);
            }

            return $record->creditNotes()->create([
                'amount_sen' => $amount,
                'reason' => $data['reason'],
                'issued_by_user_id' => $actor->id,
            ]);
        }, 3);
    }

    private function assertIssued(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            throw ValidationException::withMessages([
                'amount' => 'Refunds and credit notes are only available for issued invoices.',
            ]);
        }
    }

    private function amount(string $amount): int
    {
        $value = Invoice::toSen($amount);
        if ($value === 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }

        return $value;
    }
}
