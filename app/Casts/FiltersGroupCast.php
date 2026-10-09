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
        if (array_key_exists('relation', $data)) {
            return new FiltersGroup(filters: [$this->hydrateRelationFilter($data)]);
        }

        if (array_key_exists('filters', $data)) {
            if (! is_array($data['filters'])) {
                throw new InvalidArgumentException('The filters of a group must be a list of conditions.');
            }

            $nested = [];

            foreach ($data['filters'] as $item) {
                $nested[] = $this->hydrateNode($item);
            }

            $operator = WhereClause::tryFrom(mb_strtolower((string) ($data['operator'] ?? 'and')))
                ?? throw new InvalidArgumentException('Unknown operator of a filters group.');

            return new FiltersGroup(filters: $nested, operator: $operator);
        }

        if (array_key_exists('property', $data)) {
            return new FiltersGroup(filters: [$this->hydrateFilter($data)]);
        }

        if (array_is_list($data)) {
            $items = [];

            foreach ($data as $item) {
                $items[] = $this->hydrateNode($item);
            }

            return new FiltersGroup(filters: $items);
        }

        throw new InvalidArgumentException('Invalid filters JSON structure for FiltersGroup.');
    }

    /**
     * A node is a relation node (`relation` key), a group (`filters` key) or a filter (`property` key). Anything
     * else is refused: a condition skipped or guessed at inside an AND group would widen access.
     */
    private function hydrateNode(mixed $item): Filter|FiltersGroup|RelationFilter
    {
        if (! is_array($item)) {
            throw new InvalidArgumentException('Every item of a filters list must be a filter, a group or a relation filter.');
        }

        if (array_key_exists('relation', $item)) {
            return $this->hydrateRelationFilter($item);
        }

        if (array_key_exists('filters', $item)) {
            return $this->hydrateGroup($item);
        }

        if (array_key_exists('property', $item)) {
            return $this->hydrateFilter($item);
        }

        throw new InvalidArgumentException('Invalid filter node in filters JSON.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrateRelationFilter(array $data): RelationFilter
    {
        $relation = $data['relation'] ?? null;
        $nested = $data['filters'] ?? null;
        $morph_types = $data['morph_types'] ?? null;

        if (! is_string($relation) || $relation === '') {
            throw new InvalidArgumentException('A relation filter needs the name of the relation.');
        }

        if (! is_array($nested) || $nested === [] || array_is_list($nested)) {
            throw new InvalidArgumentException('The nested filters of a relation filter must be a filters group, not a list of conditions.');
        }

        if ($morph_types !== null && (! is_array($morph_types) || $morph_types === [] || ! array_is_list($morph_types))) {
            throw new InvalidArgumentException('The morph types of a relation filter must be a non-empty list.');
        }

        $group = [];

        foreach ($nested as $key => $value) {
            if (is_string($key)) {
                $group[$key] = $value;
            }
        }

        $types = null;

        foreach ($morph_types ?? [] as $type) {
            if (! is_string($type) || $type === '') {
                throw new InvalidArgumentException('The morph types of a relation filter must be class names.');
            }

            $types[] = $type;
        }

        return new RelationFilter(
            relation: $relation,
            filters: $this->hydrateGroup($group),
            morph_types: $types,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrateFilter(array $data): Filter
    {
        $operator_raw = $data['operator'] ?? '=';

        $operator = $operator_raw instanceof FilterOperator
            ? $operator_raw
            : FilterOperator::tryFrom((string) $operator_raw)
                ?? throw new InvalidArgumentException('Unknown operator of a filter.');

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
        return $group->toArray();
    }
}
