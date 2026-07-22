<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use function property_exists;

use DateTimeInterface;
// use Thiagoprz\CompositeKey\HasCompositeKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Arr;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Versioning\Contracts\VersionWriterInterface;
use Modules\Core\Versioning\Data\VersionChange;
use Overtrue\LaravelVersionable\Version;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

// use Illuminate\Database\Eloquent\Relations\Pivot;
// use Illuminate\Database\Eloquent\Relations\MorphMany;
// use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @phpstan-type HasVersionsType HasVersions
 */
trait HasVersions
{
    use Versionable;

    protected ?User $_creator;

    protected ?User $_modifier;

    // protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected array $dontVersionable = ['created_at', 'updated_at'/* , 'deleted_at' */, 'last_login_at'];

    protected bool $asyncVersioning = true;

    protected array $encryptedVersionable = [];

    /**
     * @var array{strategy: VersionStrategy, keys: list<string>, original: array<string, mixed>}|null
     */
    private ?array $pending_version_update = null;

    /**
     * @var array{strategy: VersionStrategy, original: array<string, mixed>}|null
     */
    private ?array $pending_version_delete = null;

    /**
     * L1 in-memory cache for version strategy resolution.
     * Keyed by model class name, value is the resolved VersionStrategy or false (disabled).
     * Eliminates repeated deserialization of the persistent cache collection on every call.
     *
     * @var array<class-string, VersionStrategy|false>
     */
    private static array $version_strategy_cache = [];

    /**
     * Reset the L1 version strategy cache.
     * Used in tests and long-running processes to clear stale state.
     */
    public static function resetVersionStrategyCache(): void
    {
        self::$version_strategy_cache = [];
    }

    /**
     * Laravel boots recursively imported traits. This override keeps vendor
     * helpers and relations while preventing its independent writer and purge.
     */
    public static function bootVersionable(): void
    {
        // Lifecycle persistence is owned by bootHasVersions().
    }

    public function shouldBeVersioning(): bool
    {
        $version_strategy = $this->getVersionStrategy();

        if ($version_strategy === false) {
            return false;
        }

        // xxx: fix break change
        if (method_exists($this, 'shouldVersioning')) {
            return call_user_func([$this, 'shouldVersioning']);
        }

        $versionableAttributes = $this->getVersionableAttributes($version_strategy);

        // no need to count already existent versions
        return Arr::hasAny($this->getDirty(), array_keys($versionableAttributes));
    }

    public function getOriginalVersionableAttributes(VersionStrategy $strategy, array $replacements = []): array
    {
        $versionable = $this->getVersionable();
        $dontVersionable = $this->getDontVersionable();

        $refreshed = $this->getRefreshedModel($this);
        $originalRaw = $refreshed->getRawOriginal();

        $keys = match ($strategy) {
            VersionStrategy::DIFF => array_keys($this->getDirty()),
            VersionStrategy::SNAPSHOT => array_keys($refreshed->attributesToArray()),
        };

        $attributes = Arr::only($originalRaw, $keys);

        if (count($versionable) > 0) {
            $attributes = Arr::only($attributes, $versionable);
        }

        return Arr::except(array_merge($attributes, $replacements), $dontVersionable);
    }

    /**
     * @param  array<string, mixed>  $replacements
     * @param  string|DateTimeInterface|null  $time
     * @param  bool  $purgeOldVersionsAfterCreate  When true, removes all other version rows for this record after the new version is stored (used with snapshot checkpoints).
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function createVersion(array $replacements = [], $time = null, bool $force = false, ?VersionStrategy $strategyForThisVersion = null, bool $purgeOldVersionsAfterCreate = false): ?Version
    {
        if ($this->getVersionStrategy() === false) {
            return null;
        }

        $effective_strategy = $strategyForThisVersion ?? $this->getVersionStrategy();

        if (! $force && ! $this->shouldBeVersioning() && $replacements === []) {
            return null;
        }

        return resolve(VersionWriterInterface::class)->write(VersionChange::forModel(
            model: $this,
            replacements: $replacements,
            time: $time,
            strategy: $effective_strategy,
            userId: $this->getVersionUserId(),
            encryptedAttributes: $this->encryptedVersionable,
        ));
    }

    /**
     * Persist a full SNAPSHOT of the current database row as a new version row (even when there are no dirty attributes).
     * Use for checkpoints when the model normally uses DIFF, or on a schedule.
     *
     * @param  array<string, mixed>  $replacements
     * @param  string|DateTimeInterface|null  $time
     * @param  bool  $purgeOldVersions  When true, deletes every other version row for this record after the snapshot is stored (full history compaction).
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function createSnapshotVersion(array $replacements = [], mixed $time = null, bool $purgeOldVersions = false): ?Version
    {
        if ($this->getVersionStrategy() === false) {
            return null;
        }

        $this->refresh();

        return $this->createVersion($replacements, $time, true, VersionStrategy::SNAPSHOT, $purgeOldVersions);
    }

    /**
     * @param  Model&HasVersions  $model
     */
    public function createInitialVersion(Model $model): ?Version
    {
        $version_strategy = $this->getVersionStrategy();

        if ($version_strategy === false) {
            return null;
        }

        // if ($model->has('versions')) {
        //     return $model->firstVersion()->first();
        // }

        /** @var Model&HasVersions $model */
        $contents = $model->filterVersionableImage($model->getAttributes());

        return resolve(VersionWriterInterface::class)->write(new VersionChange(
            model: $model,
            type: VersionChangeType::Created,
            originalContents: [],
            contents: VersionChange::encryptSelected($contents, $model->encryptedVersionable),
            strategy: VersionStrategy::SNAPSHOT,
            time: $model->updated_at,
            userId: $model->getVersionUserId(),
            encryptedAttributes: $model->encryptedVersionable,
        ));
    }

    public function getVersionUserId()
    {
        $user_key = $this->getUserForeignKeyName();

        if (isset($this['attributes'][$user_key])) {
            return $this->getAttribute($this->getUserForeignKeyName());
        }

        // return auth()->id();
        return null;
    }

    public function getVersionStrategy(): VersionStrategy|false
    {
        if (property_exists($this, 'versionStrategy')) {
            $configured = $this->versionStrategy;

            if ($configured !== null && $configured !== '') {
                return $configured instanceof VersionStrategy ? $configured : VersionStrategy::from((string) $configured);
            }
        }

        $model_class = static::class;

        // L1: static in-memory map — zero-cost after first resolution per request
        if (array_key_exists($model_class, self::$version_strategy_cache)) {
            return self::$version_strategy_cache[$model_class];
        }

        $settings_name = "version_strategy_{$this->getTable()}";

        $raw = app(PerModelSettingResolver::class)->value($settings_name, false);

        $strategy = $raw === false
            ? false
            : ($raw instanceof VersionStrategy ? $raw : VersionStrategy::from((string) $raw));

        // Store in L1 for subsequent calls within the same request
        self::$version_strategy_cache[$model_class] = $strategy;

        return $strategy;
    }

    protected static function bootHasVersions(): void
    {
        static::created(function (Model $model): void {
            /** @var Model&HasVersions $model */
            if (static::$versioning) {
                $model->createInitialVersion($model);
            }
        });

        static::updating(function (Model $model): void {
            /** @var Model&HasVersions $model */
            $model->capturePendingVersionUpdate();
        });

        static::updated(function (Model $model): void {
            /** @var Model&HasVersions $model */
            $model->writePendingVersionUpdate();
        });

        static::deleting(function (Model $model): void {
            /** @var Model&HasVersions $model */
            $model->capturePendingVersionDelete();
        });

        static::deleted(function (Model $model): void {
            /** @var Model&HasVersions $model */
            $model->writePendingVersionDelete();
        });
    }

    // public function getCreatorAttribute(): ?User
    // {
    //     if (! isset($this->creator)) {
    //         $this->creator = $this->created_by;
    //     }

    //     return $this->creator;
    // }

    /**
     * alias for created_by.
     */
    protected function creator(): Attribute
    {
        return $this->createdBy();
    }

    protected function createdBy(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! isset($this->_creator)) {
                    $first_version = $this->firstVersion?->{$this->getUserForeignKeyName()};
                    $this->_creator = $first_version ? $this->getUser($first_version) : null;
                }

                return $this->_creator;
            },
        );
    }

    protected function modifiedBy(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! isset($this->_modifier)) {
                    $last_version = $this->lastVersion?->{$this->getUserForeignKeyName()};
                    $this->_modifier = $last_version ? $this->getUser($last_version) : null;
                }

                return $this->_modifier;
            },
        );
    }

    private function capturePendingVersionUpdate(): void
    {
        $this->pending_version_update = null;
        $strategy = $this->getVersionStrategy();

        if (! static::$versioning || ! $strategy instanceof VersionStrategy) {
            return;
        }

        if (method_exists($this, 'shouldVersioning') && ! $this->shouldVersioning()) {
            return;
        }

        $keys = $this->filterVersionableKeys(array_keys($this->getDirty()), includeDeletedAt: true);

        if ($keys === []) {
            return;
        }

        $original = $strategy === VersionStrategy::SNAPSHOT
            ? $this->filterVersionableImage($this->getRawOriginal())
            : Arr::only($this->getRawOriginal(), $keys);

        $this->pending_version_update = compact('strategy', 'keys', 'original');
    }

    private function writePendingVersionUpdate(): void
    {
        $pending = $this->pending_version_update;
        $this->pending_version_update = null;

        if ($pending === null) {
            return;
        }

        $contents = $pending['strategy'] === VersionStrategy::SNAPSHOT
            ? $this->filterVersionableImage($this->getAttributes())
            : Arr::only($this->getAttributes(), $pending['keys']);

        resolve(VersionWriterInterface::class)->write(new VersionChange(
            model: $this,
            type: VersionChangeType::Updated,
            originalContents: VersionChange::encryptSelected($pending['original'], $this->encryptedVersionable),
            contents: VersionChange::encryptSelected($contents, $this->encryptedVersionable),
            strategy: $pending['strategy'],
            userId: $this->getVersionUserId(),
            encryptedAttributes: $this->encryptedVersionable,
        ));
    }

    private function capturePendingVersionDelete(): void
    {
        $this->pending_version_delete = null;
        $strategy = $this->getVersionStrategy();

        if (! static::$versioning || ! $strategy instanceof VersionStrategy) {
            return;
        }

        $this->pending_version_delete = [
            'strategy' => $strategy,
            'original' => $this->filterVersionableImage($this->getRawOriginal()),
        ];
    }

    private function writePendingVersionDelete(): void
    {
        $pending = $this->pending_version_delete;
        $this->pending_version_delete = null;

        if ($pending === null) {
            return;
        }

        resolve(VersionWriterInterface::class)->write(new VersionChange(
            model: $this,
            type: VersionChangeType::Deleted,
            originalContents: VersionChange::encryptSelected($pending['original'], $this->encryptedVersionable),
            contents: [],
            strategy: $pending['strategy'],
            userId: $this->getVersionUserId(),
            encryptedAttributes: $this->encryptedVersionable,
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterVersionableImage(array $attributes): array
    {
        return Arr::only($attributes, $this->filterVersionableKeys(array_keys($attributes)));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function filterVersionableKeys(array $keys, bool $includeDeletedAt = false): array
    {
        $versionable = $this->getVersionable();

        if ($versionable !== []) {
            $allowed = $includeDeletedAt ? [...$versionable, 'deleted_at'] : $versionable;
            $keys = array_values(array_intersect($keys, $allowed));
        }

        return array_values(array_diff($keys, $this->getDontVersionable()));
    }

    private function getUser(int $userId): ?User
    {
        $user_class = user_class();

        /** @var User $user */
        $user = new $user_class;
        $user->setConnection($this->getConnection()->getName());

        return $user->newQueryWithoutScopes()->find($userId);
    }

    // // TODO: sarà sicuramente da overridare per via delle primary key multiple
    // public function versions(): MorphMany
    // {
    // 	$version_model = $this->getVersionModel();
    // 	if (class_uses_trait($version_model, HasCompositeKey::class) || is_a($version_model, Pivot::class)) {
    // 		//TODO: da finire di scrivere, devo in qualche modo
    // 		return $this->morphMany($version_model, 'versionable', 'versionable_type', 'versionable_id');
    // 	}
    // 	return $this->morphMany($version_model, 'versionable');
    // }
}
