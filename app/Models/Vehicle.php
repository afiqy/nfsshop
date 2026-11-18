<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Vehicle extends Model
{
    use HasFactory;
    use TenantConnection;

    protected $fillable = [
        'customer_id', 'plate_number', 'chassis_number', 
        'engine_number', 'brand', 'model', 'year', 'color'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
