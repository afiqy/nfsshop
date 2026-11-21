<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InvoiceCreditNote extends Model
{
    use TenantConnection;

    protected $fillable = [
        'invoice_id',
        'amount_sen',
        'reason',
        'issued_by_user_id',
    ];

    protected $casts = ['amount_sen' => 'integer'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function number(): string
    {
        return sprintf('CN-%06d', $this->getKey());
    }
}
