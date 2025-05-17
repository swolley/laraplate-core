<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use DateTimeInterface;
// use Thiagoprz\CompositeKey\HasCompositeKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
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

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected array $dontVersionable = ['created_at', 'updated_at', 'deleted_at', 'last_login_at'];

    /**
     * @param  string|DateTimeInterface|null  $time
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function createVersion(array $replacements = [], $time = null): ?Version
    {
        if ($this->shouldBeVersioning() || $replacements !== []) {
            return tap(config('versionable.version_model')::createForModel($this, $replacements, $time), function (): void {
                $this->removeOldVersions((int) $this->getKeepVersionsCount());
            });
        }

        return null;
    }

    public function createInitialVersion(Model $model): Version
    {
        /** @var Versionable|Model $refreshedModel */
        $refreshedModel = static::query()->withoutGlobalScopes()->findOrFail($model->getKey());

        /**
         * As initial version should include all $versionable fields,
         * we need to get the latest version from database.
         * so we force to create a snapshot version.
         */
        $attributes = $refreshedModel->getVersionableAttributes(VersionStrategy::SNAPSHOT);

        return config('versionable.version_model')::createForModel($refreshedModel, $attributes, $refreshedModel->updated_at);
    }

    public function getVersionUserId()
    {
        $user_key = $this->getUserForeignKeyName();

        if (isset($this['attributes'][$user_key])) {
            return $this->getAttribute($this->getUserForeignKeyName());
        }

        return auth()->id();
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

    protected function getCreatedBy(): ?User
    {
        $first_version = $this->firstVersion?->{$this->getUserForeignKeyName()};

        return $first_version ? $this->getuser($first_version) : null;
    }

    protected function getModifiedBy(): ?User
    {
        $last_version = $this->lastVersion?->{$this->getUserForeignKeyName()};

        return $last_version ? $this->getuser($last_version) : null;
    }

    private function getUser(int $userId): ?User
    {
        $user_class = user_class();

        return $user_class::withoutGlobalScopes()->find($userId);
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
