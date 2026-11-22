<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Promotion extends Model
{
    use TenantConnection;

    protected $fillable = ['title', 'description', 'starts_on', 'ends_on', 'active'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'active' => 'boolean'];
}
