<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_interval',
        'trial_days',
        'included_workshops',
        'max_workshops',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'included_workshops' => 'integer',
        'max_workshops' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SubscriptionPlan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection');
    }

    public function workshopSubscriptions(): HasMany
    {
        return $this->hasMany(WorkshopSubscription::class);
    }
}
