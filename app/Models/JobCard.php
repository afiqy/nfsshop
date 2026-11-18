<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    protected $fillable = ['customer_id', 'vehicle_id', 'calendar_id', 'description', 'status', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

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

    public function nextStatus(): ?string
    {
        return match ($this->status) {
            self::OPEN => self::IN_PROGRESS,
            self::IN_PROGRESS => self::COMPLETED,
            default => null,
        };
    }
}
