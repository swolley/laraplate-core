<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use function property_exists;

use DateTimeInterface;
// use Thiagoprz\CompositeKey\HasCompositeKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Events\ModelVersioningRequested;
use Modules\Core\Jobs\CreateVersionJob;
use Modules\Core\Models\Setting;
use Modules\Core\Services\VersioningService;
use Overtrue\LaravelVersionable\Version;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

// use Illuminate\Database\Eloquent\Relations\Pivot;
// use Illuminate\Database\Eloquent\Relations\MorphMany;
// use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @phpstan-type HasVersionsType HasVersions
 */
trait HasVersions
{
    use Versionable {
        Versionable::shouldBeVersioning as private internalShouldBeVersioning;
        Versionable::createInitialVersion as private internalCreateInitialVersion;
    }

    protected ?User $_creator;

    protected ?User $_modifier;

    // protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected array $dontVersionable = ['created_at', 'updated_at'/* , 'deleted_at' */, 'last_login_at'];

    protected bool $asyncVersioning = true;

    protected array $encryptedVersionable = [];

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

        if ($this->asyncVersioning) {
            event(new ModelVersioningRequested(static::class, $this->getKey(), $this->getConnectionName(), $this->getTable(), $this->getAttributes(), $replacements, $this->getVersionUserId(), (int) $this->getKeepVersionsCount(), $this->encryptedVersionable, $effective_strategy, $time, $purgeOldVersionsAfterCreate));

            dispatch(new CreateVersionJob(modelClass: static::class, modelId: $this->getKey(), modelConnection: $this->getConnectionName(), table: $this->getTable(), attributes: $this->getAttributes(), replacements: $replacements, userId: $this->getVersionUserId(), keepVersionsCount: (int) $this->getKeepVersionsCount(), encryptedVersionable: $this->encryptedVersionable, versionStrategy: $effective_strategy, time: $time, purgeOldVersionsAfterCreate: $purgeOldVersionsAfterCreate))->afterCommit();

            return null;
        }

        return resolve(VersioningService::class)->createVersion(
            modelClass: static::class,
            modelId: $this->getKey(),
            connection: $this->getConnectionName(),
            table: $this->getTable(),
            attributes: $this->getAttributes(),
            replacements: $replacements,
            userId: $this->getVersionUserId(),
            keepVersionsCount: (int) $this->getKeepVersionsCount(),
            encryptedVersionable: $this->encryptedVersionable,
            versionStrategy: $effective_strategy,
            time: $time,
            purgeOldVersionsAfterCreate: $purgeOldVersionsAfterCreate,
        );
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

        /** @var Versionable|Model $refreshedModel */
        $refreshedModel = static::query()->withoutGlobalScopes()->findOrFail($model->getKey());

        /**
         * As initial version should include all $versionable fields,
         * we need to get the latest version from database.
         * so we force to create a snapshot version.
         */
        $attributes = $refreshedModel->getVersionableAttributes(VersionStrategy::SNAPSHOT);

        return config('versionable.version_model')::createForModel(
            $refreshedModel,
            $attributes,
            $refreshedModel->updated_at,
            VersionStrategy::SNAPSHOT,
        );
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

            return $configured instanceof VersionStrategy ? $configured : VersionStrategy::from((string) $configured);
        }

        $settings_name = "version_strategy_{$this->getTable()}";

        $raw = Cache::rememberForever('version_strategies', fn () => Setting::where('group_name', 'versioning')->get())->firstWhere('name', $settings_name)?->value ?? false;

        if ($raw === false) {
            return false;
        }

        return $raw instanceof VersionStrategy ? $raw : VersionStrategy::from((string) $raw);
    }

    protected static function bootHasVersions(): void
    {
        static::deleted(function (Model $model): void {
            /** @var Versionable|Version $model */
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                $model->createVersion(['deleted_at' => $model->deleted_at]);
            }
        });

        if (class_uses_trait(static::class, SoftDeletes::class)) {
            /** @phpstan-ignore staticMethod.notFound */
            static::restored(function (Model $model): void {
                $model->createVersion(['deleted_at' => null]);
            });
        }
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

    private function getUser(int $userId): ?User
    {
        $user_class = user_class();

        return $user_class::query()->withoutGlobalScopes()->find($userId);
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
