<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Mail\Mailable;

class InvoicePaymentReminderEmail extends Mailable
{
    public function __construct(public Invoice $invoice, public int $balanceSen) {}

    public function build(): self
    {
        return $this->subject('Payment reminder: '.$this->invoice->number().' from '.$this->invoice->workshop_name)
            ->view('emails.invoices.payment-reminder');
    }
}
