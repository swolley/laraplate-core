<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Contracts\IHidesAttributesFromAnonymous;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Models\Concerns\HasDynamicContents;
use Modules\Core\Versioning\Contracts\VersionWriterInterface;
use Modules\Core\Versioning\Data\VersionChange;
use Override;
use Overtrue\LaravelVersionable\Version as OvertrueVersion;
use Overtrue\LaravelVersionable\VersionStrategy;

final class Version extends OvertrueVersion
{
    use HasFactory;

    /**
     * A password hash of the algorithms PHP's password_hash() produces (bcrypt, argon2i, argon2id).
     */
    private const string PASSWORD_HASH_PATTERN = '/^\$(2[aby]|argon2id?)\$/';

    /**
     * The columns in which {@see HasDynamicContents} stores the dynamic fields and their schema.
     *
     * @var list<string>
     */
    private const array DYNAMIC_CONTENTS_COLUMNS = ['components', 'shared_components', 'entity_id', 'presettable_id'];

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

    /**
     * The version as a client receives it. Its contents are the raw attributes of the versioned record, so every
     * attribute the versioned model's own serialization withholds is masked in them: what the model hides from
     * everybody (passwords, remember tokens, two-factor secrets) and, for the anonymous user, what it hides from
     * the anonymous user. Any password hash left in a value is masked as well.
     */
    #[Override]
    public function toArray(): mixed
    {
        $serialized = parent::toArray();
        $masked = $this->maskedVersionableAttributes();

        foreach (['contents', 'original_contents', 'versionable_data'] as $key) {
            $data = $serialized[$key] ?? null;

            if (! is_array($data)) {
                continue;
            }

            foreach ($data as $attribute => &$value) {
                if (in_array($attribute, $masked, true) || (is_string($value) && preg_match(self::PASSWORD_HASH_PATTERN, $value) === 1)) {
                    $value = '[hidden]';
                }
            }

            unset($value);
            $serialized[$key] = $data;
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

    /**
     * The attributes of the versioned model that its serialization withholds from the current caller.
     *
     * @return list<string>
     */
    private function maskedVersionableAttributes(): array
    {
        $type = $this->getAttributes()['versionable_type'] ?? null;

        if (! is_string($type) || $type === '') {
            return [];
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_subclass_of($class, Model::class)) {
            return [];
        }

        $model = new $class();
        $masked = $model->getHidden();

        // Dynamic contents hide their storage columns only because their own serialization flattens them into
        // fields: they hold the record's editorial values, which its history is for, not secrets.
        if (in_array(HasDynamicContents::class, class_uses_recursive($model), true)) {
            $masked = array_diff($masked, self::DYNAMIC_CONTENTS_COLUMNS);
        }

        if ($model instanceof IHidesAttributesFromAnonymous) {
            $user = Auth::user();

            if ($user instanceof User && $user->isGuest()) {
                array_push($masked, ...$model->anonymousHiddenAttributes());
            }
        }

        return array_values(array_filter($masked, is_string(...)));
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
