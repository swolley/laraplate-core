<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Casts;

use BadMethodCallException;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Grids\Requests\GridRequest;

final class GridRequestData extends ListRequestData
{
    public readonly ?string $globalSearch;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $funnelsFilters;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $optionsFilters;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $changes;

    /**
     * @var array<string, mixed>
     */
    public readonly array $layout;

    /**
     * @param  array<string, mixed>  $validated
     * @param  string|array<string>  $primaryKey
     */
    public function __construct(public readonly GridAction $action, GridRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);
        $this->layout = self::extractLayout($validated, $mainEntity);
        $this->fixQueryParamsNames($request, $primaryKey);

        if (GridAction::isReadAction($this->action->value)) {
            $this->globalSearch = self::extractGlobalSearchFilters($validated);
            $this->funnelsFilters = $this->extractFunnelsFilters($validated);
            $this->optionsFilters = $this->extractOptionsFilters($validated);
            $this->changes = null;
        } else {
            $this->globalSearch = null;
            $this->funnelsFilters = null;
            $this->optionsFilters = null;
            $this->changes = self::extractChanges($validated);
        }
    }

    /**
     * replace "." with "_" in primary key name because of PHP automatic replacement in query params.
     *
     * @param  string|array<int, string>  $primaryKeyName
     * @return string|array<int, string>
     */
    private static function replacePrimaryKeyUnderscores(string|array $primaryKeyName): array|string
    {
        if (is_string($primaryKeyName)) {
            return str_replace('.', '_', $primaryKeyName);
        }

        return array_map(
            fn (string $key): string => str_replace('.', '_', $key),
            $primaryKeyName,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function extractLayout(array $filters, string $tableName): array
    {
        $layout = $filters['layout'] ?? ['grid_name' => $tableName];

        return is_array($layout) ? $layout : ['grid_name' => $tableName];
    }

    /**
     * extract global search filter.
     *
     * @param  array<string, mixed>  $filters
     */
    private static function extractGlobalSearchFilters(array $filters): ?string
    {
        $search = $filters['search'] ?? null;

        return is_string($search) ? $search : null;
    }

    /**
     * @param  array<mixed, mixed>  $list
     */
    private function matchCorrectFilterData(array &$list, string $defaultOperator): void
    {
        foreach ($list as $property => &$filter) {
            if (! is_array($filter)) {
                continue;
            }

            if (! isset($filter['property'])) {
                $filter['property'] = is_string($property) ? $property : (string) $property;
            }

            if (! isset($filter['operator'])) {
                $filter['operator'] = $defaultOperator;
            }
        }
    }

    /**
     * extract funnels and relative search filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>|null
     */
    private function extractFunnelsFilters(array $filters): ?array
    {
        $funnels_filters = $filters['funnels'] ?? null;

        if (! is_array($funnels_filters)) {
            return null;
        }

        $this->matchCorrectFilterData($funnels_filters, 'in');

        foreach ($funnels_filters as &$funnel) {
            if (! is_array($funnel)) {
                continue;
            }

            $value = $funnel['value'] ?? null;

            if ($value === [''] || $value === null) {
                $funnel['value'] = [];
            } elseif (is_string($value)) {
                $decoded = json_decode($value, true);

                $funnel['value'] = is_array($decoded) ? $decoded : [];
            }
        }

        return self::listOfFilterMaps($funnels_filters);
    }

    /**
     * extract options and relative search filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>|null
     */
    private function extractOptionsFilters(array $filters): ?array
    {
        $options_filters = $filters['options'] ?? null;

        if (! is_array($options_filters)) {
            return null;
        }

        $this->matchCorrectFilterData($options_filters, 'like');

        return self::listOfFilterMaps($options_filters);
    }

    /**
     * extract changes.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    private static function extractChanges(array $filters): ?array
    {
        $changes = $filters['changes'] ?? null;

        if (! is_array($changes)) {
            return null;
        }

        $normalized = [];

        foreach ($changes as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<mixed, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private static function listOfFilterMaps(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $filter) {
            if (is_array($filter)) {
                $normalized[] = $filter;
            }
        }

        return $normalized;
    }

    /**
     * fixes unwanted underscores in qurey params names.
     *
     * @param  string|array<int, string>  $modelPrimaryKey
     */
    private function fixQueryParamsNames(GridRequest $request, string|array $modelPrimaryKey): void
    {
        // - modifica di 1 record: la pk può essere string o un array<string>
        // - modifica di N record: la pk può essere array<string> o array<string[]>
        if (is_string($modelPrimaryKey)) {
            $modelPrimaryKey = [$modelPrimaryKey];
        }

        /** @var array<int, string> $replaced */
        $replaced = self::replacePrimaryKeyUnderscores($modelPrimaryKey);

        throw_if((in_array($this->action, [GridAction::Update, GridAction::Delete, GridAction::ForceDelete], true)) && empty($replaced), BadMethodCallException::class, 'PrimaryKey is mandatory for update and delete actions');

        // TODO: da finire di scrivere
        $count = count($replaced);
        $all = $request->query->all();

        for ($i = 0; $i < $count; $i++) {
            if (! array_key_exists($replaced[$i], $all)) {
                continue;
            }

            $key_name = $modelPrimaryKey[$i];
            $primary = $all[$replaced[$i]];
            $request->query->add([$key_name => $primary]);
            $request->query->remove($replaced[$i]);
        }
    }
}
