<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class AppointmentRequest extends Model
{
    use TenantConnection;

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['customer_id', 'vehicle_id', 'calendar_id', 'start', 'end', 'service_quote', 'status'];

    protected $casts = ['service_quote' => 'array'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }
}
