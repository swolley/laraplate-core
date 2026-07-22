<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Version;
use Modules\Core\Versioning\Contracts\VersionWriterInterface;
use Modules\Core\Versioning\Data\VersionChange;
use Overtrue\LaravelVersionable\VersionStrategy;

final class VersioningService
{
    public function __construct(private readonly VersionWriterInterface $writer) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $replacements
     * @param  int|string|array<array-key, mixed>|null  $modelId
     * @param  list<string>  $encryptedVersionable
     */
    public function createVersion(
        string $modelClass,
        string|int|array|null $modelId,
        ?string $connection,
        string $table,
        array $attributes,
        array $replacements = [],
        ?int $userId = null,
        int $keepVersionsCount = 0,
        array $encryptedVersionable = [],
        VersionStrategy|string|null $versionStrategy = null,
        string|DateTimeInterface|null $time = null,
        bool $purgeOldVersionsAfterCreate = false,
    ): Version {
        /** @var Model&object $model */
        $model = resolve($modelClass);

        if ($connection !== null) {
            $model->setConnection($connection);
        }

        $model->setTable($table);
        $model->setRawAttributes($attributes);
        $model->syncOriginal();

        if ($model->getKey() === null && ! is_array($modelId)) {
            $model->setAttribute($model->getKeyName(), $modelId);
            $model->syncOriginalAttribute($model->getKeyName());
        }

        if ($versionStrategy !== null) {
            $model->versionStrategy = is_string($versionStrategy) && enum_exists(VersionStrategy::class)
                ? VersionStrategy::from($versionStrategy)
                : $versionStrategy;
        }

        return $this->writer->write(VersionChange::forModel(
            model: $model,
            replacements: $replacements,
            time: $time,
            strategy: $model->versionStrategy instanceof VersionStrategy ? $model->versionStrategy : null,
            userId: $userId,
            encryptedAttributes: $encryptedVersionable,
        ));
    }
}
