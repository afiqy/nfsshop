<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class AppointmentNotification extends Model
{
    use TenantConnection;

    protected $fillable = [
        'customer_id', 'vehicle_id', 'calendar_id', 'event', 'recipient_type', 'service',
        'vehicle_plate', 'start', 'end', 'reminder_start', 'status', 'recipient_email',
        'failure_message', 'sent_at', 'resolved_by_user_id', 'resolved_at',
    ];

    protected $casts = ['sent_at' => 'datetime', 'resolved_at' => 'datetime', 'resolved_by_user_id' => 'integer'];
}
