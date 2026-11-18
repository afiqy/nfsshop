<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSubscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'additional_workshops',
        'external_subscription_id',
        'current_period_ends_at',
        'last_checked_at',
        'provider_payload',
    ];

    protected $casts = [
        'additional_workshops' => 'integer',
        'current_period_ends_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'provider_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function allowedWorkshops(): int
    {
        $allowed = $this->plan->included_workshops + $this->additional_workshops;

        return $this->plan->max_workshops === null
            ? $allowed
            : min($allowed, $this->plan->max_workshops);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true);
    }
}