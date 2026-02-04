<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\UsesTenantConnection;

class Customer extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = ['name', 'email', 'phone', 'address'];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
