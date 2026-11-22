<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Calendar extends Model
{
    use HasFactory;
    use TenantConnection;

    public const TIMEZONE = 'Asia/Kuala_Lumpur';
    public const STATUS_SCHEDULED = 'Scheduled';
    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'customer_id', 'vehicle_id', 'title', 'service_type',
        'start', 'end', 'status', 'service_quote',
    ];

    protected $casts = ['service_quote' => 'array'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function jobCard()
    {
        return $this->hasOne(JobCard::class);
    }
}
