<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;

final class VersionSetManagerAffinityArticle extends Model
{
    public const string TABLE = 'core_test_affinity_version_set_articles';

    protected $connection = 'version_set_affinity';

    protected $table = self::TABLE;

    protected $guarded = [];
}
