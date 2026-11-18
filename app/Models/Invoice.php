<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Invoice extends Model
{
    use TenantConnection;

    public const MAX_ITEMS = 25;

    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'workshop_name',
        'customer_name',
        'customer_address',
        'vehicle_plate',
        'items',
        'total_sen',
    ];

    protected $casts = [
        'items' => 'array',
        'total_sen' => 'integer',
    ];

    public function number(): string
    {
        return sprintf('INV-%06d', $this->getKey());
    }

    public static function formatAmount(int $sen): string
    {
        return sprintf('RM %s.%02d', number_format(intdiv($sen, 100)), $sen % 100);
    }
}
