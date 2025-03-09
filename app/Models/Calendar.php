<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    use HasFactory;
    //  Define the table name (only if it does not follow Laravel's convention)
    protected $table = 'calendars';
    
    protected $fillable = [
        'customer_id', 'vehicle_id', 'title', 'service_type', 
        'start', 'end', 'status'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}

