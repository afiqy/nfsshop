<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Invoice extends Model
{
    use TenantConnection;

    public const MAX_ITEMS = 25;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'workshop_name',
        'customer_name',
        'customer_address',
        'vehicle_plate',
        'due_date',
        'items',
        'total_sen',
        'status',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected $hidden = ['myinvois_buyer'];

    protected $casts = [
        'items' => 'array',
        'total_sen' => 'integer',
        'paid_sen' => 'integer',
        'due_date' => 'immutable_date',
        'voided_at' => 'datetime',
        'voided_by_user_id' => 'integer',
        'myinvois_buyer' => 'encrypted:array',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function scopeOverdue(Builder $query, string $today): Builder
    {
        return $query->where('status', self::STATUS_ISSUED)
            ->where('due_date', '<', $today)
            ->where('total_sen', '>', InvoicePayment::query()
                ->selectRaw('COALESCE(SUM(amount_sen), 0)')
                ->whereColumn('invoice_payments.invoice_id', 'invoices.id'));
    }

    public function isOverdue(int $paid, string $today): bool
    {
        return ! $this->isVoided()
            && $this->due_date !== null && $this->due_date->toDateString() < $today
            && $paid < $this->total_sen;
    }

    public function paymentStatus(int $paid): string
    {
        if ($this->isVoided()) {
            return 'Voided';
        }

        return match (true) {
            $this->total_sen === 0 => 'No payment due',
            $paid === $this->total_sen => 'Paid',
            $paid > 0 => 'Partially paid',
            default => 'Unpaid',
        };
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function balanceDue(int $paid): int
    {
        return $this->isVoided() ? 0 : max(0, $this->total_sen - $paid);
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
