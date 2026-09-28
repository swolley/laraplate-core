<?php

declare(strict_types=1);

namespace Modules\Core\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\ISearchableContributor;
use Modules\Core\Search\Schema\FieldDefinition;

/**
 * Core-owned registry of {@see ISearchableContributor}s keyed by the target
 * searchable model class (M4a). Populated at boot by contributing modules; an
 * empty registry is a safe no-op, so wiring it into the `Searchable` trait
 * changes nothing until a contributor is registered. Core stays agnostic of who
 * contributes.
 */
final class SearchableContributorRegistry
{
    /**
     * @var array<class-string, list<ISearchableContributor>>
     */
    private array $contributors = [];

    public function register(ISearchableContributor $contributor): void
    {
        $this->contributors[$contributor->contributesTo()][] = $contributor;
    }

    /**
     * Merged document fields contributed for a model instance.
     *
     * @return array<string, mixed>
     */
    public function fieldsFor(Model $model): array
    {
        $fields = [];

        foreach ($this->forClass($model::class) as $contributor) {
            $fields = array_merge($fields, $contributor->searchableFields($model));
        }

        return $fields;
    }

    /**
     * Merged index-mapping field definitions contributed for a model class.
     *
     * @param  class-string  $modelClass
     * @return list<FieldDefinition>
     */
    public function mappingFor(string $modelClass): array
    {
        $mapping = [];

        foreach ($this->forClass($modelClass) as $contributor) {
            foreach ($contributor->searchableMapping() as $field) {
                $mapping[] = $field;
            }
        }

        return $mapping;
    }

    /**
     * Contributors registered for a class or any of its parents.
     *
     * @param  class-string  $modelClass
     * @return list<ISearchableContributor>
     */
    private function forClass(string $modelClass): array
    {
        $matched = [];

        foreach ($this->contributors as $target => $list) {
            if ($modelClass === $target || is_subclass_of($modelClass, $target)) {
                foreach ($list as $contributor) {
                    $matched[] = $contributor;
                }
            }
        }

        return $matched;
    }
}
