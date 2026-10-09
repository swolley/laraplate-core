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

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(sprintf('The stored filters of [%s] are not valid JSON.', $key));
            }

            if ($decoded === null) {
                return null;
            }

            $value = $decoded;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException(sprintf('The stored filters of [%s] must be a JSON object or list.', $key));
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
     * @param  array<mixed>  $data
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

            $group_operator = $data['operator'] ?? 'and';
            $operator = is_string($group_operator) ? WhereClause::tryFrom(mb_strtolower($group_operator)) : null;

            if (! $operator instanceof WhereClause) {
                throw new InvalidArgumentException('Unknown operator of a filters group.');
            }

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

        $types = null;

        foreach ($morph_types ?? [] as $type) {
            if (! is_string($type) || $type === '') {
                throw new InvalidArgumentException('The morph types of a relation filter must be class names.');
            }

            $types[] = $type;
        }

        $group = $this->hydrateGroup($nested);

        if (! $this->holdsCondition($group)) {
            throw new InvalidArgumentException('The nested filters of a relation filter must hold at least one condition.');
        }

        return new RelationFilter(
            relation: $relation,
            filters: $group,
            morph_types: $types,
        );
    }

    /**
     * Whether the group constrains anything: an empty group, or one made of empty groups, matches every row.
     */
    private function holdsCondition(FiltersGroup $group): bool
    {
        foreach ($group->filters as $node) {
            if (! $node instanceof FiltersGroup || $this->holdsCondition($node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrateFilter(array $data): Filter
    {
        $operator_raw = $data['operator'] ?? '=';
        $property = $data['property'];

        if (! is_string($property) || $property === '') {
            throw new InvalidArgumentException('The property of a filter must be a column name.');
        }

        if ($operator_raw instanceof FilterOperator) {
            $operator = $operator_raw;
        } else {
            $operator = is_string($operator_raw) ? FilterOperator::tryFrom($operator_raw) : null;
        }

        if (! $operator instanceof FilterOperator) {
            throw new InvalidArgumentException('Unknown operator of a filter.');
        }

        return new Filter(
            property: $property,
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
