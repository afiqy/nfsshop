<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class JobCardPart extends Model
{
    use TenantConnection;

    public const TYPE_USED = 'used';

    public const TYPE_RETURNED = 'returned';

    protected $fillable = [
        'inventory_item_id',
        'inventory_movement_id',
        'return_for_id',
        'recorded_by_user_id',
        'type',
        'item_sku',
        'item_name',
        'quantity',
    ];

    protected $casts = ['quantity' => 'integer'];

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'return_for_id');
    }

    public function usage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'return_for_id');
    }
}
