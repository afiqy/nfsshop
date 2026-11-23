<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ConfirmEmailChange extends Mailable
{
    public function __construct(public string $confirmationUrl) {}

    public function build(): self
    {
        return $this->subject('Confirm your new account email')->view('emails.confirm-email-change');
    }
}
