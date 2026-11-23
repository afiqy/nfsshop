<?php

namespace App\Mail;

use App\Models\AppointmentNotification;
use Illuminate\Mail\Mailable;

class AppointmentNotificationEmail extends Mailable
{
    public function __construct(public AppointmentNotification $notification, public string $workshopName) {}

    public function build(): self
    {
        return $this->subject('Appointment update from '.$this->workshopName)
            ->view('emails.appointment-notification');
    }
}
