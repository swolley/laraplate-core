<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Date;
use Override;
use Overtrue\LaravelVersionable\Version as OvertrueVersion;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * @property VersionStrategy $version_strategy
 *
 * @mixin IdeHelperVersion
 */
final class Version extends OvertrueVersion
{
    use HasFactory;

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     */
    #[Override]
    protected $hidden = [
        'user_id',
        'connection_ref',
        'table_ref',
        'versionable_type',
        'versionable_id',
    ];

    /**
     * @param  array<string, mixed>  $replacements
     *
     * @psalm-suppress MoreSpecificReturnType
     */
    #[Override]
    public static function createForModel(Model $model, array $replacements = [], mixed $time = null, ?VersionStrategy $strategyUsed = null): self
    {
        // parent logic because it's not possible to bypass the save action

        $versionClass = $model->getVersionModel();
        $versionConnection = $model->getConnectionName();
        $userForeignKeyName = $model->getUserForeignKeyName();

        $strategy = $strategyUsed ?? self::resolveStrategyFromModel($model);

        $version = new $versionClass();
        $version->setConnection($versionConnection);

        $version->versionable_id = $model->getKey();
        $version->versionable_type = $model->getMorphClass();
        $version->{$userForeignKeyName} = $model->getVersionUserId();
        $version->version_strategy = $strategy;
        $version->contents = $model->getVersionableAttributes($strategy, $replacements);
        $version->original_contents = method_exists($model, 'getOriginalVersionableAttributes')
            ? $model->getOriginalVersionableAttributes($strategy, $replacements)
            : [];

        if ($time) {
            $version->created_at = Date::parse($time);
        }

        if (self::isDynamicEntity($model)) {
            $version->connection_ref = $model->getConnectionName();
            $version->table_ref = $model->getTable();
        }

        $version->save();

        /** @psalm-suppress LessSpecificReturnStatement */
        return $version;
    }

    /**
     * Strategy used when this version row was written; used so revert can follow per-step semantics
     * even if the versionable model later changes global strategy.
     */
    public function getStoredVersionStrategy(): VersionStrategy
    {
        return $this->version_strategy instanceof VersionStrategy
            ? $this->version_strategy
            : VersionStrategy::DIFF;
    }

    /**
     * Strategy to apply for replay when reverting toward this version (falls back to the versionable's current strategy).
     */
    public function resolveReplayVersionStrategy(Model $versionable): VersionStrategy
    {
        if ($this->version_strategy instanceof VersionStrategy) {
            return $this->version_strategy;
        }

        if (method_exists($versionable, 'getVersionStrategy')) {
            /** @var VersionStrategy|false $current */
            $current = $versionable->getVersionStrategy();

            if ($current instanceof VersionStrategy) {
                return $current;
            }
        }

        return VersionStrategy::DIFF;
    }

    #[Override]
    public function revertWithoutSaving(): ?Model
    {
        $versionable = $this->getCompleteVersionable();

        if (! $versionable instanceof Model) {
            return null;
        }

        $original = $versionable->getRawOriginal();

        switch ($this->resolveReplayVersionStrategy($versionable)) {
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

        // mask hashed values from json_encode (guard against null/non-iterable)
        $data = $serialized['versionable_data'] ?? null;

        if (is_array($data)) {
            foreach ($data as &$value) {
                if (gettype($value) === 'string' && mb_strlen($value) === 60 && preg_match('/^\$2y\$/', $value)) {
                    $value = '[hidden]';
                }
            }

            $serialized['versionable_data'] = $data;
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
            'version_strategy' => VersionStrategy::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }

    private static function resolveStrategyFromModel(Model $model): VersionStrategy
    {
        if (! method_exists($model, 'getVersionStrategy')) {
            return VersionStrategy::DIFF;
        }

        /** @var VersionStrategy|false|string $strategy */
        $strategy = $model->getVersionStrategy();

        if ($strategy instanceof VersionStrategy) {
            return $strategy;
        }

        if ($strategy === false) {
            return VersionStrategy::DIFF;
        }

        return VersionStrategy::from((string) $strategy);
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
