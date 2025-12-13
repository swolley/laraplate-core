<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Version;
use Overtrue\LaravelVersionable\VersionStrategy;

final class VersioningService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $replacements
     * @param  array<int, string>  $encryptedVersionable
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
        mixed $time = null,
    ): ?Version {
        /** @var Model&object $model */
        $model = app($modelClass);

        if ($connection !== null) {
            $model->setConnection($connection);
        }

        $model->setTable($table);
        $model->setRawAttributes($attributes);
        $model->syncOriginal();

        if ($versionStrategy !== null) {
            $model->versionStrategy = is_string($versionStrategy) && enum_exists(VersionStrategy::class)
                ? VersionStrategy::from($versionStrategy)
                : $versionStrategy;
        }

        /** @var class-string<Version> $versionModel */
        $versionModel = config('versionable.version_model');

        /** @var Version $version */
        $version = $versionModel::createForModel($model, $replacements, $time);
        $needsSave = false;

        if ($userId !== null) {
            $version->{$model->getUserForeignKeyName()} = $userId;
            $needsSave = true;
        }

        if ($encryptedVersionable !== []) {
            $contents = $version->contents;

            foreach ($encryptedVersionable as $field) {
                if (array_key_exists($field, $contents)) {
                    $contents[$field] = encrypt($contents[$field]);
                }
            }

            $version->contents = $contents;
            $needsSave = true;
        }

        if ($needsSave) {
            $version->save();
        }

        if ($keepVersionsCount > 0) {
            $model->versions()
                ->latest()
                ->skip($keepVersionsCount)
                ->get()
                ->each->delete();
        }

        return $version;
    }
}
