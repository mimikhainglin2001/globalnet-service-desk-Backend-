<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Records created/updated/deleted changes into audit_logs.
 *
 * Model events fire inside the caller's transaction, so the audit row is
 * committed or rolled back together with the change it describes.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $new = $model->filterAuditable($model->getAttributes());

            if ($new !== []) {
                $model->writeAudit('created', null, $new);
            }
        });

        static::updated(function (Model $model) {
            $new = $model->filterAuditable($model->getChanges());

            if ($new === []) {
                return;
            }

            $old = [];
            foreach (array_keys($new) as $key) {
                $old[$key] = $model->getRawOriginal($key);
            }

            $model->writeAudit('updated', $old, $new);
        });

        static::deleted(function (Model $model) {
            $model->writeAudit('deleted', $model->filterAuditable($model->getRawOriginal()), null);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Attributes to record. Null means "all except auditExclude()".
     *
     * @return list<string>|null
     */
    protected function auditOnly(): ?array
    {
        return null;
    }

    /**
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['id', 'created_at', 'updated_at', 'password', 'remember_token'];
    }

    protected function filterAuditable(array $attributes): array
    {
        $only = $this->auditOnly();

        $filtered = $only === null
            ? array_diff_key($attributes, array_flip($this->auditExclude()))
            : array_intersect_key($attributes, array_flip($only));

        return array_map(
            fn ($value) => $value instanceof BackedEnum ? $value->value : $value,
            $filtered,
        );
    }

    protected function writeAudit(string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
