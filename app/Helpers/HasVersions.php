<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
// use Thiagoprz\CompositeKey\HasCompositeKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Modules\Core\Events\ModelVersioningRequested;
use Modules\Core\Jobs\CreateVersionJob;
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
    use Versionable;

    protected ?User $_creator;

    protected ?User $_modifier;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected array $dontVersionable = ['created_at', 'updated_at', 'deleted_at', 'last_login_at'];

    protected bool $asyncVersioning = true;

    protected array $encryptedVersionable = [];

    /**
     * @param  string|DateTimeInterface|null  $time
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function createVersion(array $replacements = [], $time = null): ?Version
    {
        if (! $this->shouldBeVersioning() && $replacements === []) {
            return null;
        }

        if ($this->asyncVersioning) {
            event(new \Modules\Core\Events\ModelVersioningRequested(static::class, $this->getKey(), $this->getConnectionName(), $this->getTable(), $this->getAttributes(), $replacements, $this->getVersionUserId(), (int) $this->getKeepVersionsCount(), $this->encryptedVersionable, $this->versionStrategy, $time));

            dispatch(new \Modules\Core\Jobs\CreateVersionJob(modelClass: static::class, modelId: $this->getKey(), modelConnection: $this->getConnectionName(), table: $this->getTable(), attributes: $this->getAttributes(), replacements: $replacements, userId: $this->getVersionUserId(), keepVersionsCount: (int) $this->getKeepVersionsCount(), encryptedVersionable: $this->encryptedVersionable, versionStrategy: $this->versionStrategy, time: $time))->afterCommit();

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
            versionStrategy: $this->versionStrategy,
            time: $time,
        );
    }

    /**
     * @param  Model&HasVersions  $model
     */
    public function createInitialVersion(Model $model): Version
    {
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
