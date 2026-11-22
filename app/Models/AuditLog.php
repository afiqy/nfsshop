<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'user_id',
        'event',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'origin',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $event, Model $model, ?User $user = null, array $payload = []): self
    {
        $request = request();

        return static::on(config('tenancy.database.central_connection'))->create([
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'user_id' => $user?->id,
            'event' => $event,
            'old_values' => $payload['old_values'] ?? null,
            'new_values' => $payload['new_values'] ?? null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'origin' => $payload['origin'] ?? ($request?->route()?->getName() ?? 'system'),
        ]);
    }

    public function scopeFilter($query, array $filters)
    {
        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query;
    }

    public function scopeSuspicious($query)
    {
        return $query->whereIn('event', [
            'login.failed',
            'account.locked',
            'permission.denied',
            'user.suspended',
        ]);
    }
}
