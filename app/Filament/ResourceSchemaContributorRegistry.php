<?php

declare(strict_types=1);

namespace Modules\Core\Filament;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Contracts\IResourceSchemaContributor;

/**
 * Core-owned registry of {@see IResourceSchemaContributor}s keyed by the target
 * model class (M22). Populated at boot by contributing modules; an empty registry
 * is a safe no-op, so a resource that consults it renders unchanged until a
 * contributor registers. The UI twin of {@see \Modules\Core\Search\SearchableContributorRegistry};
 * Core stays agnostic of who contributes.
 */
final class ResourceSchemaContributorRegistry
{
    /**
     * @var array<class-string, list<IResourceSchemaContributor>>
     */
    private array $contributors = [];

    public function register(IResourceSchemaContributor $contributor): void
    {
        $this->contributors[$contributor->contributesTo()][] = $contributor;
    }

    /**
     * Infolist sections contributed for a record, in registration order.
     *
     * @return list<mixed>
     */
    public function infolistSectionsFor(Model $record): array
    {
        $sections = [];

        foreach ($this->forClass($record::class) as $contributor) {
            foreach ($contributor->infolistSections($record) as $section) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * Record actions contributed for a record, in registration order.
     *
     * @return list<mixed>
     */
    public function recordActionsFor(Model $record): array
    {
        $actions = [];

        foreach ($this->forClass($record::class) as $contributor) {
            foreach ($contributor->recordActions($record) as $action) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Contributors registered for a class or any of its parents.
     *
     * @param  class-string  $modelClass
     * @return list<IResourceSchemaContributor>
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
