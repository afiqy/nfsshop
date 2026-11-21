<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InvoicePaymentSubmission extends Model
{
    use TenantConnection;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'invoice_id',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'invoice_payment_id',
        'payment_method',
        'amount_sen',
        'reference',
        'proof_path',
        'proof_mime_type',
        'status',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'amount_sen' => 'integer',
        'reviewed_by_user_id' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }
}
