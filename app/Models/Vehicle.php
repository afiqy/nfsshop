<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\UsesTenantConnection;

class Vehicle extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'customer_id', 'plate_number', 'chassis_number', 
        'engine_number', 'brand', 'model', 'year', 'color'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
