<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class VehicleReminderAttempt extends Model
{
    use TenantConnection;

    protected $fillable = ['vehicle_reminder_id', 'requested_by_user_id', 'resolved_by_user_id', 'recipient_email', 'status', 'failure_message', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];
}
