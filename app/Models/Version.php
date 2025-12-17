<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Date;
use Override;
use Overtrue\LaravelVersionable\Version as OvertrueVersion;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * @mixin IdeHelperVersion
 */
final class Version extends OvertrueVersion
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     */
    protected $hidden = [
        'user_id',
        'connection_ref',
        'table_ref',
        'versionable_type',
        'versionable_id',
    ];

    /**
     * @psalm-suppress MoreSpecificReturnType
     */
    #[Override]
    public static function createForModel(Model $model, $replacements = [], $time = null): self
    {
        // parent logic because it's not possible to bypass the save action

        $versionClass = $model->getVersionModel();
        $versionConnection = $model->getConnectionName();
        $userForeignKeyName = $model->getUserForeignKeyName();

        $version = new $versionClass();
        $version->setConnection($versionConnection);

        $version->versionable_id = $model->getKey();
        $version->versionable_type = $model->getMorphClass();
        $version->{$userForeignKeyName} = $model->getVersionUserId();
        $version->contents = $model->getVersionableAttributes($model->getVersionStrategy(), $replacements);
        $version->original_contents = method_exists($model, 'getOriginalVersionableAttributes')
            ? $model->getOriginalVersionableAttributes($model->getVersionStrategy(), $replacements)
            : [];

        if ($time) {
            $version->created_at = Date::parse($time);
        }

        if (self::isDynamicEntity($model)) {
            $version->connection_ref = $model->getConnection();
            $version->table_ref = $model->getTable();
        }

        $version->save();

        /** @psalm-suppress LessSpecificReturnStatement */
        return $version;
    }

    #[Override]
    public function revertWithoutSaving(): ?Model
    {
        $versionable = $this->getCompleteVersionable();

        if (! $versionable instanceof Model) {
            return null;
        }

        $original = $versionable->getRawOriginal();

        switch ($versionable->getVersionStrategy()) {
            case VersionStrategy::DIFF:
                // v1 + ... + vN
                /** @var OvertrueVersion $version */
                foreach ($this->previousVersions()->orderOldestFirst()->get() as $version) {
                    if ($version->contents !== []) {
                        $versionable->setRawAttributes(array_merge($original, $version->contents));
                    }
                }

                break;
            case VersionStrategy::SNAPSHOT:
                // v1 + vN
                /** @var OvertrueVersion $initVersion */
                $initVersion = $versionable->versions()->first();

                if ($initVersion->contents !== []) {
                    $versionable->setRawAttributes(array_merge($original, $initVersion->contents));
                }
        }

        if ($this->contents !== []) {
            $versionable->setRawAttributes(array_merge($original, $this->contents));
        }

        return $versionable;
    }

    /**
     * The previous versions that belong to the version.
     *
     * @return MorphMany<Version>
     */
    #[Override]
    public function previousVersions(): MorphMany
    {
        $versionable = $this->getCompleteVersionable();

        return $versionable->latestVersions()
            ->where(function (Builder $query): void {
                $query->where('created_at', '<', $this->created_at)
                    ->orWhere(function (Builder $q): void {
                        $q->where('id', '<', $this->getKey())
                            ->where('created_at', '<=', $this->created_at);
                    });
            });
    }

    #[Override]
    public function nextVersion(): ?static
    {
        $versionable = $this->getCompleteVersionable();

        if (! $versionable instanceof Model) {
            return null;
        }

        return $versionable->versions()
            ->where(function (Builder $query): void {
                $query->where('created_at', '>', $this->created_at)
                    ->orWhere(function (Builder $q): void {
                        $q->where('id', '>', $this->getKey())
                            ->where('created_at', '>=', $this->created_at);
                    });
            })
            ->orderOldestFirst()
            ->first();
    }

    #[Override]
    public function toArray(): mixed
    {
        $serialized = parent::toArray();

        // mask hashed values from json_encode
        foreach ($serialized['versionable_data'] as &$value) {
            if (gettype($value) === 'string' && mb_strlen($value) === 60 && preg_match('/^\$2y\$/', $value)) {
                $value = '[hidden]';
            }
        }

        return $serialized;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contents' => 'json',
            'original_contents' => 'json',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }

    private static function isDynamicEntity(Model $model): bool
    {
        return class_exists(DynamicEntity::class) && $model instanceof DynamicEntity;
    }

    private function getCompleteVersionable(): ?Model
    {
        /** @var Model $versionable */
        $versionable = $this->versionable;

        if (! $versionable) {
            return null;
        }

        /** @phpstan-ignore property.notFound */
        if ($this->versionable_type) {
            /** @phpstan-ignore property.notFound */
            if ($this->connection_ref) {
                $versionable->setConnection($this->connection_ref);
            }

            /** @phpstan-ignore property.notFound */
            if ($this->table_ref) {
                $versionable->setTable($this->table_ref);
            }
        }

        return $versionable;
    }
}
