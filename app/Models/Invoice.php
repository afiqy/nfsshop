<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
        'job_card_id',
        'workshop_name',
        'customer_name',
        'customer_address',
        'vehicle_plate',
        'due_date',
        'items',
        'pricing',
        'total_sen',
        'status',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected $hidden = ['myinvois_buyer'];

    protected $casts = [
        'items' => 'array',
        'pricing' => 'array',
        'total_sen' => 'integer',
        'paid_sen' => 'integer',
        'refunded_sen' => 'integer',
        'credited_sen' => 'integer',
        'due_date' => 'immutable_date',
        'voided_at' => 'datetime',
        'voided_by_user_id' => 'integer',
        'myinvois_buyer' => 'encrypted:array',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(InvoiceEmailDelivery::class);
    }

    public function paymentReminders(): HasMany
    {
        return $this->hasMany(InvoicePaymentReminder::class);
    }

    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(InvoicePaymentSubmission::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(InvoiceCreditNote::class);
    }

    public function refunds(): HasManyThrough
    {
        return $this->hasManyThrough(InvoiceRefund::class, InvoicePayment::class);
    }

    public function scopeOverdue(Builder $query, string $today): Builder
    {
        return $query->where('status', self::STATUS_ISSUED)
            ->where('due_date', '<', $today)
            ->whereRaw('invoices.total_sen - COALESCE((
                SELECT SUM(invoice_credit_notes.amount_sen)
                FROM invoice_credit_notes
                WHERE invoice_credit_notes.invoice_id = invoices.id
            ), 0) > (
                SELECT COALESCE(SUM(invoice_payments.amount_sen), 0) - COALESCE((
                    SELECT SUM(invoice_refunds.amount_sen)
                    FROM invoice_refunds
                    INNER JOIN invoice_payments AS refunded_payments
                        ON refunded_payments.id = invoice_refunds.invoice_payment_id
                    WHERE refunded_payments.invoice_id = invoices.id
                ), 0)
                FROM invoice_payments
                WHERE invoice_payments.invoice_id = invoices.id
            )');
    }

    public function isOverdue(int $paid, string $today, int $refunded = 0, int $credited = 0): bool
    {
        return ! $this->isVoided()
            && $this->due_date !== null && $this->due_date->toDateString() < $today
            && $this->balanceDue($paid, $refunded, $credited) > 0;
    }

    public function paymentStatus(int $paid, int $refunded = 0, int $credited = 0): string
    {
        if ($this->isVoided()) {
            return 'Voided';
        }

        if ($this->total_sen === 0 && $credited === 0) {
            return 'No payment due';
        }

        $netTotal = max(0, $this->total_sen - $credited);
        $netPaid = max(0, $paid - $refunded);

        if ($netPaid > $netTotal) {
            return 'Customer credit';
        }
        if ($netTotal === 0) {
            return 'Credited';
        }

        return match (true) {
            $netPaid === $netTotal => 'Paid',
            $netPaid > 0 => 'Partially paid',
            default => 'Unpaid',
        };
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function balanceDue(int $paid, int $refunded = 0, int $credited = 0): int
    {
        return $this->isVoided() ? 0 : max(0, $this->total_sen - $credited - $paid + $refunded);
    }

    public function customerCredit(int $paid, int $refunded = 0, int $credited = 0): int
    {
        return $this->isVoided() ? 0 : max(0, $paid - $refunded - $this->total_sen + $credited);
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
