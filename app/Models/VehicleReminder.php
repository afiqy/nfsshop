<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class VehicleReminder extends Model
{
    use TenantConnection;

    protected $fillable = ['vehicle_id', 'customer_id', 'type', 'due_on', 'note', 'completed_at'];

    protected $casts = ['due_on' => 'date', 'completed_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function attempts()
    {
        return $this->hasMany(VehicleReminderAttempt::class);
    }
}
