<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class JobCardUpdate extends Model
{
    use TenantConnection;

    public const TYPE_OPENED = 'opened';

    public const TYPE_STATUS = 'status';

    public const TYPE_NOTE = 'note';

    protected $fillable = ['actor_user_id', 'actor_name', 'type', 'status', 'note'];

    protected $casts = ['note' => 'encrypted'];
}
