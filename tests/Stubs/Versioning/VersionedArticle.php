<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasVersions;
use Overtrue\LaravelVersionable\VersionStrategy;

final class VersionedArticle extends Model
{
    use HasVersions;

    public const string TABLE = 'core_test_versioned_articles';

    protected $table = self::TABLE;

    protected $guarded = [];

    protected array $versionable = ['title'];

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    public function useVersionStrategy(VersionStrategy $strategy): void
    {
        $this->versionStrategy = $strategy;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function encryptVersionAttributes(array $attributes): void
    {
        $this->encryptedVersionable = $attributes;
    }
}
