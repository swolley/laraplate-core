<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Collection;

final class TreeCollection extends Collection
{
    private const string ROOT_KEY = '__root__';

    public function tree(): Collection
    {
        $grouped = $this->groupBy(fn (object $item): int|string => $item->parent_id ?? self::ROOT_KEY);

        return self::buildTree($grouped, self::ROOT_KEY);
    }

    public function withPaths(string $separator = ' > ', string $field = 'name'): Collection
    {
        return self::buildPaths($this, '', $separator, $field);
    }

    /**
     * Build a tree structure from grouped items.
     *
     * @param  Collection<int, Collection>  $grouped
     */
    private static function buildTree(Collection $grouped, int|string $parentId): Collection
    {
        return ($grouped[$parentId] ?? collect())->map(function (object $item) use ($grouped): object {
            $item->children = self::buildTree($grouped, $item->id)->values();

            if (isset($item->articles_count)) {
                $item->total_articles_count = $item->articles_count + $item->children->sum('total_articles_count');
            }

            return $item;
        });
    }

    /**
     * Build paths for tree items.
     */
    private static function buildPaths(Collection $items, string $prefix, string $separator, string $field): Collection
    {
        return $items->map(function (object $item) use ($prefix, $separator, $field): object {
            $current = $prefix === '' ? $item->{$field} : $prefix . $separator . $item->{$field};
            $item->path = $current;

            if ($item->children->isNotEmpty()) {
                $item->children = self::buildPaths($item->children, $current, $separator, $field);
            }

            return $item;
        });
    }
}
