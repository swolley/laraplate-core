<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

/**
 * Names a Filament resource from the lang file of the module owning its model: `{group}.entities.{table}`
 * with `singular` and `plural`, the table named without its prefix. The navigation label follows the plural.
 *
 * A `$modelLabel` or `$pluralModelLabel` written on the resource still wins, and a missing key leaves the
 * label Filament derives from the model. Translated labels are shown as the lang file writes them: Filament
 * does not title-case them.
 */
trait HasTranslatedResourceLabels
{
    /**
     * @var array<class-string, string>
     */
    private static array $entityLabelKeys = [];

    public static function getModelLabel(): string
    {
        return static::$modelLabel ?? static::translatedEntityLabel('singular') ?? parent::getModelLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? static::translatedEntityLabel('plural') ?? parent::getPluralModelLabel();
    }

    public static function hasTitleCaseModelLabel(): bool
    {
        return static::translatedEntityLabel('plural') === null && parent::hasTitleCaseModelLabel();
    }

    /**
     * The `entities` key of the model. A resource that shows a role over a table rather than the table
     * itself (CMS categories over taxonomies) overrides it.
     */
    public static function getEntityLabelKey(): string
    {
        return self::$entityLabelKeys[static::class] ??= self::entityLabelKeyFor(static::getModel());
    }

    private static function entityLabelKeyFor(string $model_class): string
    {
        /** @var Model $model */
        $model = new $model_class();
        $group = app(AttributeLabelResolver::class)->groupFor($model_class);
        $module = preg_match('/^Modules\\\\([^\\\\]+)\\\\/', $model_class, $matches) === 1 ? mb_strtolower($matches[1]) : 'core';
        $entity = (string) preg_replace('/^(?:' . preg_quote($module, '/') . '|core|vend)_/', '', $model->getTable());

        return "{$group}.entities.{$entity}";
    }

    private static function translatedEntityLabel(string $form): ?string
    {
        $key = static::getEntityLabelKey() . '.' . $form;

        if (! Lang::has($key)) {
            return null;
        }

        $line = trans($key);

        return is_string($line) ? $line : null;
    }
}
