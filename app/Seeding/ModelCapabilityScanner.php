<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Locking\Traits\HasOptimisticLocking;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Models\Concerns\HasTranslations;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\SoftDeletes\SoftDeletes;
use ReflectionClass;
use Throwable;

use function class_uses_recursive;
use function models;

final class ModelCapabilityScanner
{
    /**
     * Walk every discoverable model once, resolving its full trait set in a
     * single traversal instead of one per capability.
     *
     * @return list<ModelCapabilities>
     */
    public function scan(): array
    {
        $scanned = [];

        foreach (models() as $model_class) {
            try {
                /** @var Model $instance */
                $instance = new ReflectionClass($model_class)->newInstanceWithoutConstructor();
                $table = $instance->getTable();
            } catch (Throwable) {
                continue;
            }

            $traits = class_uses_recursive($model_class);

            $scanned[] = new ModelCapabilities(
                modelClass: $model_class,
                table: $table,
                hasVersions: isset($traits[HasVersions::class]),
                hasSoftDeletes: isset($traits[SoftDeletes::class]),
                hasLocks: isset($traits[HasLocks::class]),
                hasOptimisticLocking: isset($traits[HasOptimisticLocking::class]),
                hasTranslations: isset($traits[HasTranslations::class]),
                hasApprovals: isset($traits[HasApprovals::class]),
            );
        }

        return $scanned;
    }
}
