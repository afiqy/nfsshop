<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class PaymentPreference extends Model
{
    use TenantConnection;

    public const MAX_GATEWAYS = 2;

    protected $fillable = ['user_id', 'gateways', 'offline_methods'];

    protected $casts = ['gateways' => 'array', 'offline_methods' => 'array'];

    public function usesGateway(string $provider): bool
    {
        return in_array($provider, array_column($this->gateways, 'provider'), true);
    }
}
