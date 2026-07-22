<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\HasVersions;
use Overtrue\LaravelVersionable\VersionStrategy;

final class SoftDeletedVersionedArticle extends Model
{
    use HasVersions;
    use SoftDeletes;

    protected $table = VersionedArticle::TABLE;

    protected $guarded = [];

    protected array $versionable = ['title'];

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;
}
