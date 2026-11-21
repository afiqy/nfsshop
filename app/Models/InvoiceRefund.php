<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InvoiceRefund extends Model
{
    use TenantConnection;

    protected $fillable = [
        'invoice_payment_id',
        'amount_sen',
        'reason',
        'reference',
        'refunded_by_user_id',
    ];

    protected $casts = ['amount_sen' => 'integer'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }

    public function number(): string
    {
        return sprintf('REF-%06d', $this->getKey());
    }
}
