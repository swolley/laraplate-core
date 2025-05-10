<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Override;
use Modules\Core\Rules\QueryColumn;
use Modules\Core\Casts\SelectRequestData;

abstract class SelectRequest extends CrudRequest
{
    #[Override]
    public function rules()
    {
        return parent::rules() + [
            'columns.*' => [new QueryColumn()],
            'relations.*' => ['string'],
        ];
    }

    #[Override]
    public function parsed(): SelectRequestData
    {
        /** @phpstan-ignore method.notFound */
        return new SelectRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }

    protected static function decode(string $value): array
    {
        return is_json($value) ? json_decode($value, true) : preg_split("/,\s?/", $value);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $to_merge = [];

        $columns = $this->input('columns');
        if ($columns && is_string($columns)) {
            $to_merge['columns'] = static::decode($columns);
        }

        $relations = $this->input('relations');
        if ($relations && is_string($relations)) {
            $to_merge['relations'] = static::decode($relations);
        }

        /** @phpstan-ignore method.notFound */
        $this->merge($to_merge);
    }
}
