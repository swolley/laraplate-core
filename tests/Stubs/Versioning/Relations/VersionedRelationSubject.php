<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning\Relations;

use Illuminate\Database\Eloquent\Model;

final class VersionedRelationSubject extends Model
{
    public const string TABLE = 'core_test_versioned_relation_subjects';

    protected $table = self::TABLE;

    protected $guarded = [];
}
