<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'paid_sen' => 'integer',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function paymentStatus(int $paid): string
    {
        return match (true) {
            $this->total_sen === 0 => 'No payment due',
            $paid === $this->total_sen => 'Paid',
            $paid > 0 => 'Partially paid',
            default => 'Unpaid',
        };
    }

    public static function toSen(string $amount): int
    {
        [$ringgit, $sen] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $ringgit * 100 + (int) str_pad($sen, 2, '0');
    }

    public function number(): string
    {
        return sprintf('INV-%06d', $this->getKey());
    }

    public static function formatAmount(int $sen): string
    {
        return sprintf('RM %s.%02d', number_format(intdiv($sen, 100)), $sen % 100);
    }
}
