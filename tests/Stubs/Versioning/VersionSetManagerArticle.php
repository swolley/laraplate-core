<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VersionSetManagerArticle extends Model
{
    public const string TABLE = 'core_test_version_set_articles';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return HasMany<VersionSetManagerArticle, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
