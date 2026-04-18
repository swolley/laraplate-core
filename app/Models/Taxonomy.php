<?php

declare(strict_types=1);

namespace Modules\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Helpers\HasApprovals;
use Modules\Core\Helpers\HasPath;
use Modules\Core\Helpers\HasTranslatedDynamicContents;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\SortableTrait;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Override;
use Spatie\EloquentSortable\Sortable;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @mixin IdeHelperTaxonomy
 */
abstract class Taxonomy extends Model implements Sortable
{
    // region Traits
    use HasActivation {
        HasActivation::casts as private activationCasts;
    }
    use HasApprovals {
        HasApprovals::toArray as private approvalsToArray;
        HasApprovals::requiresApprovalWhen as private requiresApprovalWhenTrait;
    }
    use HasLocks;
    use HasPath;
    use HasRecursiveRelationships;
    use HasTranslatedDynamicContents {
        HasTranslatedDynamicContents::getRules as private getRulesDynamicContents;
        HasTranslatedDynamicContents::toArray as private translatedDynamicContentsToArray;
        HasTranslatedDynamicContents::casts as private translatedDynamicContentsCasts;
    }
    use HasValidity;
    use SortableTrait {
        SortableTrait::scopeOrdered as private scopePriorityOrdered;
    }
    // endregion

    final protected $table = 'taxonomies';

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    final protected $fillable = [
        'parent_id',
        'presettable_id',
        'entity_id',
    ];

    #[Override]
    final protected $hidden = [
        'entity',
        'parent_id',
        'presettable_id',
        'entity_id',
        'created_at',
        'updated_at',
        'ancestorsAndSelf',
        'ancestors',
        'descendants',
        'descendantsAndSelf',
        'childrenAndSelf',
        'bloodline',
    ];

    /**
     * @return class-string<Entity>
     */
    abstract public static function getEntityModelClass(): string;

    abstract protected static function getEntityType(): IDynamicEntityTypable;

    final public function getRules(): array
    {
        $rules = parent::getRules();
        $dynamic_fields = $this->getRulesDynamicContents();
        $default_rule = Model::DEFAULT_RULE;
        $rules[$default_rule] = array_merge($rules[$default_rule], $dynamic_fields, [
            'parent_id' => 'sometimes|nullable|exists:categories,id',
        ]);
        $rules['create'] = array_merge($rules['create'], [
            // 'name' => 'required|string|max:255', // Validated in translation
            // 'slug' => 'sometimes|nullable|string|max:255', // Validated in translation
            'translations' => 'sometimes|array',
            'translations.*.locale' => 'required|string|max:10',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.slug' => 'sometimes|nullable|string|max:255',
            'translations.*.components' => 'sometimes|array',
        ]);
        $rules['update'] = array_merge($rules['update'], [
            // 'name' => 'sometimes|required|string|max:255', // Validated in translation
            // 'slug' => 'sometimes|nullable|string|max:255', // Validated in translation
            'translations' => 'sometimes|array',
            'translations.*.locale' => 'required|string|max:10',
            'translations.*.name' => 'sometimes|required|string|max:255',
            'translations.*.slug' => 'sometimes|nullable|string|max:255',
            'translations.*.components' => 'sometimes|array',
        ]);

        return $rules;
    }

    #[Override]
    public function getPath(): string
    {
        // Use slug from translation and build a stable ancestor chain
        return $this->buildAncestorChain('slug', '/');
    }

    public function appendPaths(): self
    {
        $this->appends = array_merge($this->appends, ['ids', 'path', 'full_name']);

        return $this;
    }

    #[Override]
    public function toArray(): array
    {
        // $parsed = parent::toArray() ?? $this->attributesToArray();
        $parsed = parent::toArray();

        return array_merge($parsed, $this->translatedDynamicContentsToArray(), $this->approvalsToArray($parsed));
    }

    #[Override]
    protected static function booted(): void
    {
        self::addGlobalScope('global_filters', static function (Builder $query): void {
            $query->active()->valid();
        });
        self::addGlobalScope('global_ordered', static function (Builder $query): void {
            $query->ordered();
        });
    }

    /**
     * Eager-load ancestors (and their translations) for path-related accessors.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    final protected function withAncestorsForPath(Builder $query): Builder
    {
        return $query->with([
            'ancestors',
            'ancestors.translations',
        ]);
    }

    #[Scope]
    final protected function ordered(Builder $query): Builder
    {
        $model = $query->getModel();

        if (! $model instanceof self) {
            return $query;
        }

        $orderColumn = $model->qualifyColumn($model->determineOrderColumnName());

        // @phpstan-ignore method.notFound
        return $query->orderBy($orderColumn, 'asc')->validityOrdered();
    }

    protected function casts(): array
    {
        return array_merge($this->activationCasts(), $this->translatedDynamicContentsCasts(), [
            'parent_id' => 'integer',
            'model_type_id' => 'integer',
            'order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'content' => 'json',
        ]);
    }

    protected function slugPlaceholders(): array
    {
        // Use name from translation
        return [...array_map(fn (string $field): string => '{' . $field . '}', $this->dynamicSlugFields()), '{name}'];
    }

    protected function requiresApprovalWhen(array $modifications): bool
    {
        return $this->requiresApprovalWhenTrait($modifications) && ($modifications[self::$valid_from_column] ?? $modifications[self::$valid_to_column] ?? false);
    }

    final protected function ids(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->buildAncestorChain('id', '.'),
        );
    }

    final protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->buildAncestorChain('name', ' > '),
        );
    }

    final protected function title(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name,
        );
    }

    /**
     * Build a concatenated chain from ancestors plus the current model.
     */
    private function buildAncestorChain(string $field, string $separator): string
    {
        $ancestors = $this->ancestors;

        if (! $ancestors instanceof Collection) {
            $ancestors = collect();
        }

        /** @var Collection<int,mixed> $segments */
        $segments = $ancestors
            ->pluck($field)
            ->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
            ->reverse()
            ->values();

        $current = $this->{$field} ?? null;

        if ($current !== null && $current !== '') {
            $segments->push($current);
        }

        if ($segments->isEmpty()) {
            return (string) ($current ?? '');
        }

        return $segments->join($separator);
    }
}
