<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InvoicePaymentReminderAttempt extends Model
{
    use TenantConnection;

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['recipient_email', 'requested_by_user_id', 'status', 'failure_message', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];
}
