<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ILockableModel;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\Concerns\HasVersions;
use Overtrue\LaravelVersionable\VersionStrategy;

final class VersionedLockableArticle extends Model implements ILockableModel
{
    use HasLocks;
    use HasVersions;

    public const string TABLE = 'core_test_versioned_lockable_articles';

    protected $table = self::TABLE;

    protected $guarded = [];

    protected array $versionable = ['title'];

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;
}
