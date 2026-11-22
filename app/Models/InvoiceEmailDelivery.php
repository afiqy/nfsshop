<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InvoiceEmailDelivery extends Model
{
    use TenantConnection;

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'retry_of_id',
        'recipient_email',
        'sent_by_user_id',
        'status',
        'failure_message',
        'sent_at',
    ];

    protected $casts = [
        'retry_of_id' => 'integer',
        'sent_by_user_id' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }
}
