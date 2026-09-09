<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Filament\Utils\HasTable;

final class ACLsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('permission.name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('description')
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('filters')
                        ->formatStateUsing(static fn (mixed $state): string => self::describeFilters($state))
                        ->wrap()
                        ->toggleable(),
                    TextColumn::make('sort')
                        ->toggleable(),
                ]);
            },
        );
    }

    /**
     * Render a {@see FiltersGroup} as a human readable expression, e.g.
     * `status = published and (country in ["IT","DE"])`.
     */
    public static function describeFilters(mixed $state): string
    {
        if (! $state instanceof FiltersGroup) {
            return '';
        }

        return self::describeGroup($state, isRoot: true);
    }

    private static function describeGroup(FiltersGroup $group, bool $isRoot = false): string
    {
        $parts = [];

        foreach ($group->filters as $node) {
            if ($node instanceof FiltersGroup) {
                $parts[] = self::describeGroup($node);
            } elseif ($node instanceof Filter) {
                $parts[] = self::describeFilter($node);
            }
        }

        if ($parts === []) {
            return '';
        }

        $expression = implode(sprintf(' %s ', $group->operator->value), $parts);

        return ($isRoot || count($parts) === 1) ? $expression : sprintf('(%s)', $expression);
    }

    private static function describeFilter(Filter $filter): string
    {
        return sprintf('%s %s %s', $filter->property, $filter->operator->value, self::describeValue($filter->value));
    }

    private static function describeValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
