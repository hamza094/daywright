<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changes);

            unset($changes['updated_at'], $original['updated_at']);

            if (! empty($changes)) {
                app(AuditLogService::class)->log(
                    event: 'model.updated',
                    auditable: $model,
                    oldValues: $original,
                    newValues: $changes
                );
            }
        });

        static::deleted(function (Model $model): void {
            app(AuditLogService::class)->log(
                event: method_exists($model, 'isForceDeleting') && $model->isForceDeleting() ? 'model.force_deleted' : 'model.deleted',
                auditable: $model,
                oldValues: $model->toArray(),
                newValues: null
            );
        });
    }
}
