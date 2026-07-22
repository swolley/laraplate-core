<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Versioning\Contracts\VersionWriterInterface;
use Modules\Core\Versioning\Data\VersionChange;
use Override;
use Overtrue\LaravelVersionable\Version as OvertrueVersion;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * @property VersionStrategy $version_strategy
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 * @mixin IdeHelperVersion
 */
final class Version extends OvertrueVersion
{
    use HasFactory;

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::Versions->value;

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
        return resolve(VersionWriterInterface::class)->write(VersionChange::forModel(
            model: $model,
            replacements: $replacements,
            time: $time,
            strategy: $strategyUsed,
        ));
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
     * @return BelongsTo<VersionSet, Version>
     */
    public function versionSet(): BelongsTo
    {
        return $this->belongsTo(VersionSet::class);
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
            'change_type' => VersionChangeType::class,
            'contents' => 'json',
            'original_contents' => 'json',
            'subject_key' => 'json',
            'version_strategy' => VersionStrategy::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
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
