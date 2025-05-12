<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Components;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Grids\Definitions\ListEntity;
use Override;

final class Option extends ListEntity
{
    #[Override]
    protected function getData(): array
    {
        $name = $this->getValueField()->getFullAlias();
        $option_data = $this->requestData->optionsFilters[$name];

        $exploded = explode('.', (string) $name);
        $subfix = array_pop($exploded);
        $field_path = implode('.', $exploded);

        if ($field_path === $this->getPath()) {
            $model = $this->getModel();
        } elseif (($relation = $this->getRelationDeeply($field_path)) instanceof \Modules\Core\Grids\Definitions\Relation) {
            $model = $relation->getModel();
        } else {
            Log::warning('No model found for grid option ' . $name);

            return [
                new Collection(),
                0,
            ];
        }

        // TODO: da rivedere
        $this->checkColumnsOrGetDefaults($model, $subfix, $option_data['columns'] ?? [$subfix]);
        // $columns[] = $this->getValueField()->getName();
        // if (is_array($this->getLabelField())) array_push($columns, ...array_map(fn ($field) => $field->getName(), $this->getLabelField()));
        // else $columns[] = $this->getLabelField()->getName();
        // $columns = array_unique($columns);
        $columns = $this->getAllQueryFields();
        $this->getAllFields()->diff($columns);
        $this->getAllFields()->diff($columns);
        $columns = $columns->map(fn ($field): string => $field->getName())->toArray();

        $query = $model::query()->select($columns);
        $this->addSortsIntoQuery($query, $option_data['sort'] ?? $this->getDefaultSorts($columns, $model));

        if (isset($option_data['value'])) {
            self::applyCorrectWhereMethod($query, $subfix, FilterOperator::tryFrom($option_data['operator']), $option_data['value']);
        }

        // Log::debug(static::dumpQuery($query));

        $data = $query->get($columns);
        $totals = $data->count();

        return [$data, $totals];
    }
}
