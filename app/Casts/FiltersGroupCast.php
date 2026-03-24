<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Override;

/**
 * Eloquent cast for persisting {@see FiltersGroup} as JSON on the ACL (and similar) models.
 */
final class FiltersGroupCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    #[Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): ?FiltersGroup
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                return null;
            }

            $value = $decoded;
        }

        if (! is_array($value)) {
            return null;
        }

        return $this->hydrateGroup($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = $this->hydrateGroup($value);
        }

        if (! $value instanceof FiltersGroup) {
            throw new InvalidArgumentException(sprintf(
                'Attribute [%s] must be an array or an instance of %s.',
                $key,
                FiltersGroup::class,
            ));
        }

        return json_encode($this->dehydrateGroup($value), JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrateGroup(array $data): FiltersGroup
    {
        if (array_key_exists('filters', $data)) {
            $nested = [];

            foreach ($data['filters'] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $nested[] = $this->hydrateNode($item);
            }

            $operator = WhereClause::tryFrom(mb_strtolower((string) ($data['operator'] ?? 'and'))) ?? WhereClause::AND;

            return new FiltersGroup(filters: $nested, operator: $operator);
        }

        if (array_key_exists('property', $data)) {
            return new FiltersGroup(filters: [$this->hydrateFilter($data)]);
        }

        if (array_is_list($data)) {
            $items = [];

            foreach ($data as $item) {
                if (is_array($item)) {
                    $items[] = $this->hydrateNode($item);
                }
            }

            return new FiltersGroup(filters: $items);
        }

        throw new InvalidArgumentException('Invalid filters JSON structure for FiltersGroup.');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function hydrateNode(array $item): Filter|FiltersGroup
    {
        if (array_key_exists('filters', $item)) {
            return $this->hydrateGroup($item);
        }

        if (array_key_exists('property', $item)) {
            return $this->hydrateFilter($item);
        }

        if (array_is_list($item)) {
            return $this->hydrateGroup([
                'filters' => $item,
                'operator' => WhereClause::AND->value,
            ]);
        }

        throw new InvalidArgumentException('Invalid filter node in filters JSON.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrateFilter(array $data): Filter
    {
        $operator_raw = $data['operator'] ?? '=';

        $operator = $operator_raw instanceof FilterOperator
            ? $operator_raw
            : FilterOperator::tryFrom((string) $operator_raw) ?? FilterOperator::EQUALS;

        return new Filter(
            property: (string) $data['property'],
            value: $data['value'] ?? null,
            operator: $operator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dehydrateGroup(FiltersGroup $group): array
    {
        $filters = [];

        foreach ($group->filters as $node) {
            if ($node instanceof FiltersGroup) {
                $filters[] = $this->dehydrateGroup($node);
            } elseif ($node instanceof Filter) {
                $filters[] = $this->dehydrateFilter($node);
            }
        }

        return [
            'filters' => $filters,
            'operator' => $group->operator->value,
        ];
    }

    /**
     * @return array{property: string, value: mixed, operator: string}
     */
    private function dehydrateFilter(Filter $filter): array
    {
        return [
            'property' => $filter->property,
            'value' => $filter->value,
            'operator' => $filter->operator->value,
        ];
    }
}
