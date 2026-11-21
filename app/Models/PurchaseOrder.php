<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class PurchaseOrder extends Model
{
    use TenantConnection;

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public const LABELS = [
        self::STATUS_ORDERED => 'Ordered',
        self::STATUS_PARTIALLY_RECEIVED => 'Partially received',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'supplier_id',
        'supplier_name',
        'created_by_user_id',
        'status',
        'notes',
        'received_at',
    ];

    protected $casts = ['received_at' => 'datetime'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function canReceive(): bool
    {
        return in_array($this->status, [self::STATUS_ORDERED, self::STATUS_PARTIALLY_RECEIVED], true);
    }
}
