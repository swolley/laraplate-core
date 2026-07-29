<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;

final class VersionSetManagerUuidArticle extends Model
{
    public const string TABLE = 'core_test_version_set_uuid_articles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = self::TABLE;

    protected $guarded = [];
}
