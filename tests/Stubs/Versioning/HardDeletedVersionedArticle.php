<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\SoftDeletes\SoftDeletes;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * A versioned model carrying Core's soft deletes and switching them off in its own code, so that
 * a plain delete removes the row.
 */
final class HardDeletedVersionedArticle extends Model
{
    use HasVersions;
    use SoftDeletes;

    protected bool $softDeletesEnabled = false;

    protected $table = VersionedArticle::TABLE;

    protected $guarded = [];

    protected array $versionable = ['title'];

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;
}
