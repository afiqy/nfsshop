<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Mail\Mailable;

class InvoiceEmail extends Mailable
{
    public function __construct(public Invoice $invoice) {}

    public function build(): self
    {
        return $this->subject('Invoice '.$this->invoice->number().' from '.$this->invoice->workshop_name)
            ->view('emails.invoices.issued');
    }
}
