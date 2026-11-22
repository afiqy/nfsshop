<?php

namespace App\Services;

use App\Mail\InvoicePaymentReminderEmail;
use App\Models\Calendar;
use App\Models\Invoice;
use App\Models\InvoicePaymentReminder;
use App\Models\InvoicePaymentReminderAttempt;
use App\Models\InvoicePaymentSubmission;
use App\Models\PaymentReminderSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoicePaymentReminderService
{
    private const OFFSETS = [0, 7, 14];

    public function processToday(): void
    {
        if (! PaymentReminderSetting::query()->findOrFail(1)->enabled) {
            return;
        }

        $today = CarbonImmutable::today(Calendar::TIMEZONE);
        foreach (self::OFFSETS as $offset) {
            $dueDate = $today->subDays($offset)->toDateString();
            Invoice::query()->where('status', Invoice::STATUS_ISSUED)
                ->whereDate('due_date', $dueDate)
                ->lazyById()->each(function (Invoice $invoice) use ($dueDate, $offset) {
                    $attempt = $this->reserve($invoice->id, $dueDate, $offset);
                    if ($attempt) {
                        $this->deliver($invoice->id, $attempt);
                    }
                });
        }
    }

    public function retry(Invoice $invoice, InvoicePaymentReminder $reminder, User $actor): InvoicePaymentReminderAttempt
    {
        $attempt = DB::connection('tenant')->transaction(function () use ($invoice, $reminder, $actor) {
            $setting = PaymentReminderSetting::query()->lockForUpdate()->findOrFail(1);
            $record = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $stage = $record->paymentReminders()->lockForUpdate()->findOrFail($reminder->id);
            $latest = $stage->attempts()->latest('id')->first();
            if (! $setting->enabled || ! $this->eligible($record, $stage->due_date->toDateString())) {
                throw ValidationException::withMessages([
                    'reminder' => 'Enable reminders and ensure the invoice is issued, unpaid, has the same due date and has no pending payment proofs.',
                ]);
            }
            if (! $latest || $latest->status !== InvoicePaymentReminderAttempt::FAILED) {
                throw ValidationException::withMessages(['reminder' => 'Only the latest failed attempt can be retried.']);
            }

            return $stage->attempts()->create([
                'requested_by_user_id' => $actor->id,
                'status' => InvoicePaymentReminderAttempt::SENDING,
            ]);
        }, 3);

        return $this->deliver($invoice->id, $attempt);
    }

    private function reserve(int $invoiceId, string $dueDate, int $offset): ?InvoicePaymentReminderAttempt
    {
        return DB::connection('tenant')->transaction(function () use ($invoiceId, $dueDate, $offset) {
            $setting = PaymentReminderSetting::query()->lockForUpdate()->findOrFail(1);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if (! $setting->enabled || ! $this->eligible($invoice, $dueDate)
                || $invoice->paymentReminders()->where('due_date', $dueDate)->where('days_overdue', $offset)->exists()) {
                return null;
            }

            $stage = $invoice->paymentReminders()->create(['due_date' => $dueDate, 'days_overdue' => $offset]);

            return $stage->attempts()->create(['status' => InvoicePaymentReminderAttempt::SENDING]);
        }, 3);
    }

    private function eligible(Invoice $invoice, string $dueDate): bool
    {
        if ($invoice->status !== Invoice::STATUS_ISSUED || $invoice->due_date?->toDateString() !== $dueDate
            || $invoice->paymentSubmissions()->where('status', InvoicePaymentSubmission::STATUS_PENDING)->exists()) {
            return false;
        }

        return $invoice->balanceDue(
            (int) $invoice->payments()->sum('amount_sen'),
            (int) $invoice->refunds()->sum('invoice_refunds.amount_sen'),
            (int) $invoice->creditNotes()->sum('amount_sen')
        ) > 0;
    }

    private function deliver(int $invoiceId, InvoicePaymentReminderAttempt $attempt): InvoicePaymentReminderAttempt
    {
        return DB::connection('tenant')->transaction(function () use ($invoiceId, $attempt) {
            $setting = PaymentReminderSetting::query()->lockForUpdate()->findOrFail(1);
            $invoice = Invoice::query()->with('customer')->lockForUpdate()->findOrFail($invoiceId);
            $attempt = InvoicePaymentReminderAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $stage = $invoice->paymentReminders()->findOrFail($attempt->invoice_payment_reminder_id);
            if (! $setting->enabled || ! $this->eligible($invoice, $stage->due_date->toDateString())) {
                $attempt->update([
                    'status' => InvoicePaymentReminderAttempt::FAILED,
                    'failure_message' => 'Reminder paused because the invoice or workshop is no longer eligible.',
                ]);

                return $attempt;
            }

            $email = $invoice->customer?->email;
            if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $attempt->update([
                    'status' => InvoicePaymentReminderAttempt::FAILED,
                    'failure_message' => 'The customer has no valid email address. Update it before retrying.',
                ]);

                return $attempt;
            }
            $attempt->recipient_email = $email;
            $balance = $invoice->balanceDue(
                (int) $invoice->payments()->sum('amount_sen'),
                (int) $invoice->refunds()->sum('invoice_refunds.amount_sen'),
                (int) $invoice->creditNotes()->sum('amount_sen')
            );

            try {
                Mail::to($email)->send(new InvoicePaymentReminderEmail($invoice, $balance));
            } catch (Throwable $exception) {
                $attempt->fill([
                    'status' => InvoicePaymentReminderAttempt::FAILED,
                    'failure_message' => 'Mail transport failed. Check application logs before retrying.',
                ])->save();
                Log::error('Payment reminder email failed.', [
                    'tenant_id' => tenant('id'),
                    'invoice_id' => $invoice->id,
                    'attempt_id' => $attempt->id,
                    'exception_class' => $exception::class,
                ]);

                return $attempt;
            }

            $attempt->fill(['status' => InvoicePaymentReminderAttempt::SENT, 'sent_at' => now()])->save();

            return $attempt;
        });
    }
}
