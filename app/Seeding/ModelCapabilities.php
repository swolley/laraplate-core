<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Database\Eloquent\Model;

final readonly class ModelCapabilities
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public string $modelClass,
        public string $table,
        public bool $hasVersions,
        public bool $hasSoftDeletes,
        public bool $hasLocks,
        public bool $hasOptimisticLocking,
        public bool $hasTranslations,
        public bool $hasApprovals,
    ) {}
}
