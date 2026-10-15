<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Labels Filament components after the attribute they show, from the lang file of the module that owns
 * the model: `{group}.fields.{attribute}`, then the shared `app.fields.{attribute}`.
 *
 * Every field, column, entry and filter gets a label closure when it is made; a `->label()` written in the
 * code comes later and replaces it. When no key exists the closure answers null and Filament labels from the
 * attribute name as it always did. Keys follow the database names, so nothing is converted on the way.
 */
final class AttributeLabelResolver
{
    /**
     * @var array<string, string>
     */
    private array $groups = [];

    public function register(): void
    {
        Field::configureUsing(function (Field $field): void {
            if ($field->hasCustomLabel()) {
                return;
            }

            $field->label(fn (): ?string => $this->label(
                $field->hasContainer() ? $field->getModel() : null,
                $field->getName(),
                Str::afterLast($field->getName(), '.'),
            ));
        });

        Entry::configureUsing(function (Entry $entry): void {
            if ($entry->hasCustomLabel()) {
                return;
            }

            $entry->label(fn (): ?string => $this->label(
                $entry->hasContainer() ? $entry->getModel() : null,
                $entry->getName(),
                Str::afterLast($entry->getName(), '.'),
            ));
        });

        Column::configureUsing(function (Column $column): void {
            $column->label(fn (): ?string => $this->label(
                $column->hasTable() ? $column->getTable()->getModel() : null,
                $column->getName(),
                Str::of($column->getName())->beforeLast('.')->afterLast('.')->toString(),
            ));
        });

        BaseFilter::configureUsing(function (BaseFilter $filter): void {
            $filter->label(fn (): ?string => $this->label(
                $filter->getTable()->getModel(),
                $filter->getName(),
                Str::before($filter->getName(), '.'),
            ));
        });
    }

    /**
     * The translated label of an attribute, or null when no key names it.
     *
     * A relation path is looked up first as a whole, its dots turned into underscores (`customer.name` →
     * `customer_name`), then by the segment Filament labels by: the relation for a column or a filter, the
     * last part for a field or an entry.
     *
     * @param  class-string|string|null  $model_class
     */
    public function label(?string $model_class, string $name, string $segment): ?string
    {
        $group = $this->groupFor($model_class);
        $keys = [];

        if (str_contains($name, '.')) {
            $keys[] = "{$group}.fields." . str_replace('.', '_', $name);
        }

        $keys[] = "{$group}.fields.{$segment}";
        $keys[] = "app.fields.{$segment}";

        foreach (array_unique($keys) as $key) {
            if (! Lang::has($key)) {
                continue;
            }

            $line = trans($key);

            if (is_string($line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * The lang group of the module a model belongs to: `Modules\MES\...` reads `mes`; Core, the application
     * and anything outside a module read `app`.
     */
    public function groupFor(?string $model_class): string
    {
        if ($model_class === null) {
            return 'app';
        }

        return $this->groups[$model_class] ??= preg_match('/^Modules\\\\([^\\\\]+)\\\\/', $model_class, $matches) === 1
            && mb_strtolower($matches[1]) !== 'core'
                ? mb_strtolower($matches[1])
                : 'app';
    }
}
