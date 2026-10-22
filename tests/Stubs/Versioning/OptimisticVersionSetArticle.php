<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IOptimisticLockableModel;
use Modules\Core\Locking\Traits\HasOptimisticLocking;

/**
 * A root guarded by optimistic locking, to check that the version-set lock compares its version (D34).
 */
final class OptimisticVersionSetArticle extends Model implements IOptimisticLockableModel
{
    use HasOptimisticLocking;

    public const string TABLE = 'core_test_optimistic_version_set_articles';

    protected $table = self::TABLE;

    protected $guarded = [];
}
