<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class JobCard extends Model
{
    use TenantConnection;

    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const LABELS = [
        self::OPEN => 'Open',
        self::IN_PROGRESS => 'In progress',
        self::COMPLETED => 'Completed',
    ];

    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'calendar_id',
        'description',
        'status',
        'completed_at',
        'assigned_mechanic_id',
        'due_date',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'assigned_mechanic_id' => 'integer',
        'due_date' => 'immutable_date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(JobCardUpdate::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(JobCardPart::class);
    }

    public function isOverdue(string $today): bool
    {
        return in_array($this->status, [self::OPEN, self::IN_PROGRESS], true)
            && $this->due_date !== null
            && $this->due_date->toDateString() < $today;
    }

    public function nextStatus(): ?string
    {
        return match ($this->status) {
            self::OPEN => self::IN_PROGRESS,
            self::IN_PROGRESS => self::COMPLETED,
            default => null,
        };
    }
}
