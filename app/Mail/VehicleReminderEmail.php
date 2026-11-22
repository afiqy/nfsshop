<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use Illuminate\Mail\Mailable;

class VehicleReminderEmail extends Mailable
{
    public function __construct(public VehicleReminder $reminder, public Customer $customer, public Vehicle $vehicle, public string $workshopName) {}

    public function build(): self
    {
        return $this->subject('Vehicle reminder from '.$this->workshopName)->view('emails.vehicle-reminder');
    }
}
