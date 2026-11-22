<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class CustomerVehicleRequest extends Model
{
    use TenantConnection;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'customer_id',
        'requested_by_user_id',
        'plate_number',
        'chassis_number',
        'engine_number',
        'brand',
        'model',
        'year',
        'color',
        'mileage_km',
        'fuel_type',
        'status',
        'decided_by_user_id',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'mileage_km' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
