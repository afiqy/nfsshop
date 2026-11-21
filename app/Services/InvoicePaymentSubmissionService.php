<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePaymentSubmission;
use App\Models\User;
use App\Models\WorkshopPaymentQr;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InvoicePaymentSubmissionService
{
    public function __construct(private PaymentProofStorage $proofStorage) {}

    public function submitByCustomer(
        Invoice $invoice,
        Customer $customer,
        User $submitter,
        array $data,
        UploadedFile $proof
    ): InvoicePaymentSubmission {
        return $this->withProof($proof, function (string $proofPath) use ($invoice, $customer, $submitter, $data, $proof) {
            return DB::connection('tenant')->transaction(function () use (
                $invoice,
                $customer,
                $submitter,
                $data,
                $proof,
                $proofPath
            ) {
                $record = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                abort_unless((int) $record->customer_id === (int) $customer->id, 404);
                $this->assertPayable($record);

                $pendingAmount = (int) $record->paymentSubmissions()
                    ->where('status', InvoicePaymentSubmission::STATUS_PENDING)->sum('amount_sen');
                $amount = $this->amount($data['amount']);
                $this->assertWithinBalance($record, $amount, $pendingAmount);
                $qr = $this->activeQr($data['workshop_payment_qr_id']);

                return $record->paymentSubmissions()->create([
                    'submitted_by_user_id' => $submitter->id,
                    'payment_method' => $qr->label,
                    'amount_sen' => $amount,
                    'reference' => $data['reference'] ?? null,
                    'proof_path' => $proofPath,
                    'proof_mime_type' => $proof->getMimeType(),
                    'status' => InvoicePaymentSubmission::STATUS_PENDING,
                ]);
            }, 3);
        });
    }

    public function recordByAdmin(
        Invoice $invoice,
        User $admin,
        array $data,
        UploadedFile $proof
    ): InvoicePaymentSubmission {
        return $this->withProof($proof, function (string $proofPath) use ($invoice, $admin, $data, $proof) {
            return DB::connection('tenant')->transaction(function () use ($invoice, $admin, $data, $proof, $proofPath) {
                $record = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                $this->assertPayable($record);
                $amount = $this->amount($data['amount']);
                $this->assertWithinBalance($record, $amount);
                $qr = $this->activeQr($data['workshop_payment_qr_id']);

                $payment = $record->payments()->create([
                    'amount_sen' => $amount,
                    'reference' => $data['reference'] ?? null,
                    'recorded_by_user_id' => $admin->id,
                ]);

                return $record->paymentSubmissions()->create([
                    'submitted_by_user_id' => $admin->id,
                    'reviewed_by_user_id' => $admin->id,
                    'invoice_payment_id' => $payment->id,
                    'payment_method' => $qr->label,
                    'amount_sen' => $amount,
                    'reference' => $data['reference'] ?? null,
                    'proof_path' => $proofPath,
                    'proof_mime_type' => $proof->getMimeType(),
                    'status' => InvoicePaymentSubmission::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ]);
            }, 3);
        });
    }

    public function approve(InvoicePaymentSubmission $submission, User $admin): InvoicePaymentSubmission
    {
        return DB::connection('tenant')->transaction(function () use ($submission, $admin) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($submission->invoice_id);
            $record = $invoice->paymentSubmissions()->lockForUpdate()->findOrFail($submission->id);
            abort_unless($record->status === InvoicePaymentSubmission::STATUS_PENDING, 409);
            $this->assertPayable($invoice);
            $this->assertWithinBalance($invoice, $record->amount_sen);

            $payment = $invoice->payments()->create([
                'amount_sen' => $record->amount_sen,
                'reference' => $record->reference,
                'recorded_by_user_id' => $admin->id,
            ]);
            $record->forceFill([
                'invoice_payment_id' => $payment->id,
                'reviewed_by_user_id' => $admin->id,
                'status' => InvoicePaymentSubmission::STATUS_APPROVED,
                'reviewed_at' => now(),
                'review_note' => null,
            ])->save();

            return $record;
        }, 3);
    }

    public function reject(InvoicePaymentSubmission $submission, User $admin, string $reason): InvoicePaymentSubmission
    {
        return DB::connection('tenant')->transaction(function () use ($submission, $admin, $reason) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($submission->invoice_id);
            $record = $invoice->paymentSubmissions()->lockForUpdate()->findOrFail($submission->id);
            abort_unless($record->status === InvoicePaymentSubmission::STATUS_PENDING, 409);
            $record->forceFill([
                'reviewed_by_user_id' => $admin->id,
                'status' => InvoicePaymentSubmission::STATUS_REJECTED,
                'review_note' => $reason,
                'reviewed_at' => now(),
            ])->save();

            return $record;
        }, 3);
    }

    private function activeQr(int|string $id): WorkshopPaymentQr
    {
        $qr = WorkshopPaymentQr::query()->where('is_active', true)->find($id);
        if (! $qr) {
            throw ValidationException::withMessages([
                'workshop_payment_qr_id' => 'Select an active payment QR code.',
            ]);
        }

        return $qr;
    }

    private function assertPayable(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            throw ValidationException::withMessages([
                'amount' => 'Payment submissions are only available for issued invoices.',
            ]);
        }
    }

    private function assertWithinBalance(Invoice $invoice, int $amount, int $reserved = 0): void
    {
        $paid = (int) $invoice->payments()->sum('amount_sen');
        if ($amount > $invoice->balanceDue($paid) - $reserved) {
            throw ValidationException::withMessages([
                'amount' => 'The payment exceeds the available invoice balance.',
            ]);
        }
    }

    private function amount(string $amount): int
    {
        $value = Invoice::toSen($amount);
        if ($value === 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a payment greater than zero.']);
        }

        return $value;
    }

    private function withProof(UploadedFile $proof, Closure $operation): InvoicePaymentSubmission
    {
        $path = $this->proofStorage->store($proof);
        $persisted = false;
        try {
            $submission = $operation($path);
            $persisted = true;

            return $submission;
        } finally {
            if (! $persisted && ! $this->proofStorage->delete($path)) {
                Log::warning('A payment proof file could not be removed after a failed submission.', [
                    'path' => $path,
                ]);
            }
        }
    }
}
