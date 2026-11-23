<?php

namespace App\Services;

use App\Mail\InvoiceEmail;
use App\Models\Invoice;
use App\Models\InvoiceEmailDelivery;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceEmailDeliveryService
{
    private const FAILURE_MESSAGE = 'The configured mail transport could not send this email. Check application logs before retrying.';

    public function __construct(private UserNotificationPreferences $preferences) {}

    public function send(Invoice $invoice, User $actor): InvoiceEmailDelivery
    {
        $record = $this->loadInvoice($invoice);
        $this->assertSendable($record);
        $this->assertEmailAllowed($record);
        $recipient = $record->customer?->email;
        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'email' => 'Add a valid email address to this invoice customer before sending.',
            ]);
        }

        return $this->deliver($record, $recipient, $actor);
    }

    public function retry(Invoice $invoice, InvoiceEmailDelivery $delivery, User $actor): InvoiceEmailDelivery
    {
        $record = $this->loadInvoice($invoice);
        $this->assertSendable($record);
        $this->assertEmailAllowed($record);
        $failedDelivery = $record->emailDeliveries()->findOrFail($delivery->id);
        if ($failedDelivery->status !== InvoiceEmailDelivery::STATUS_FAILED) {
            throw ValidationException::withMessages([
                'email' => 'Only a failed invoice email attempt can be retried.',
            ]);
        }

        return $this->deliver($record, $failedDelivery->recipient_email, $actor, $failedDelivery);
    }

    private function loadInvoice(Invoice $invoice): Invoice
    {
        return Invoice::query()
            ->with('customer')
            ->withSum('payments as paid_sen', 'amount_sen')
            ->withSum('refunds as refunded_sen', 'amount_sen')
            ->withSum('creditNotes as credited_sen', 'amount_sen')
            ->findOrFail($invoice->id);
    }

    private function assertSendable(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            throw ValidationException::withMessages([
                'email' => 'Only issued invoices can be emailed.',
            ]);
        }
    }

    private function assertEmailAllowed(Invoice $invoice): void
    {
        if (! $this->preferences->allowsEmail($invoice->customer?->portal_user_id, UserProfile::EMAIL_INVOICES)) {
            throw ValidationException::withMessages([
                'email' => 'The customer has opted out of invoice emails.',
            ]);
        }
    }

    private function deliver(
        Invoice $invoice,
        string $recipient,
        User $actor,
        ?InvoiceEmailDelivery $retryOf = null
    ): InvoiceEmailDelivery {
        $delivery = $invoice->emailDeliveries()->create([
            'retry_of_id' => $retryOf?->id,
            'recipient_email' => $recipient,
            'sent_by_user_id' => $actor->id,
            'status' => InvoiceEmailDelivery::STATUS_SENDING,
        ]);

        try {
            Mail::to($recipient)->send(new InvoiceEmail($invoice));
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => InvoiceEmailDelivery::STATUS_FAILED,
                'failure_message' => self::FAILURE_MESSAGE,
            ])->save();

            Log::error('Invoice email attempt failed.', [
                'invoice_id' => $invoice->id,
                'delivery_id' => $delivery->id,
                'exception_class' => $exception::class,
            ]);

            return $delivery;
        }

        $delivery->forceFill([
            'status' => InvoiceEmailDelivery::STATUS_SENT,
            'sent_at' => now(),
        ])->save();

        return $delivery;
    }
}
