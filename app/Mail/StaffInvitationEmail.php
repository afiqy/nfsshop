<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class StaffInvitationEmail extends Mailable
{
    public function __construct(public string $invitationUrl, public string $workshopName, public string $role) {}

    public function build(): self
    {
        return $this->subject('Workshop staff invitation')->view('emails.staff-invitation');
    }
}
