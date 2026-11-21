<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InventoryMovement extends Model
{
    use TenantConnection;

    protected $fillable = [
        'purchase_order_item_id',
        'invoice_id',
        'quantity_change',
        'balance_after',
        'recorded_by_user_id',
        'note',
    ];

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
