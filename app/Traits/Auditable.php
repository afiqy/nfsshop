<?php
namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->recordAudit('created', [], $model->getAttributes());
        });

        static::updating(function (Model $model) {
            $model->recordAudit('updated', $model->getOriginal(), $model->getDirty());
        });

        static::deleting(function (Model $model) {
            $model->recordAudit('deleted', $model->getOriginal(), []);
        });
    }

    protected function recordAudit(string $event, array $oldValues, array $newValues): void
    {
        $attributes = $this->getAuditableAttributes();
        $oldValues = Arr::only($oldValues, $attributes);
        $newValues = Arr::only($newValues, $attributes);

        if ($event === 'updated' && empty($oldValues) && empty($newValues)) {
            return;
        }

        AuditLog::record(
            $event,
            $this,
            Auth::user(),
            [
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
            ]
        );
    }

    public function getAuditableAttributes(): array
    {
        if (property_exists($this, 'auditable') && is_array($this->auditable)) {
            return $this->auditable;
        }

        $attributes = $this->getFillable();
        if (empty($attributes)) {
            $attributes = array_keys($this->getAttributes());
        }

        return array_values(array_diff($attributes, ['created_at', 'updated_at', 'deleted_at']));
    }
}