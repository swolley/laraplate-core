<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Collection;

final class TreeCollection extends Collection
{
    public function tree()
    {
        $grouped = $this->groupBy('parent_id');

        $buildTree = function ($parentId) use (&$buildTree, $grouped) {
            return ($grouped[$parentId] ?? collect())->map(function ($item) use ($buildTree) {
                $item->children = $buildTree($item->id)->values();

                if (isset($item->articles_count)) {
                    $item->total_articles_count = $item->articles_count + $item->children->sum('total_articles_count');
                }

                return $item;
            });
        };

        return $buildTree(null);
    }

    public function withPaths(string $separator = ' > ', string $field = 'name')
    {
        $buildPaths = function ($items, $prefix = '') use (&$buildPaths, $separator, $field) {
            return $items->map(function ($item) use ($prefix, $buildPaths, $separator, $field) {
                $current = $prefix === '' ? $item->{$field} : $prefix . $separator . $item->{$field};
                $item->path = $current;

                if ($item->children->isNotEmpty()) {
                    $item->children = $buildPaths($item->children, $current);
                }

                return $item;
            });
        };

        return $buildPaths($this);
    }
}
