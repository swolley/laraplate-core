<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Override;
use Modules\Core\Rules\QueryColumn;
use Modules\Core\Casts\SelectRequestData;

abstract class SelectRequest extends CrudRequest
{
    #[Override]
    final public function rules()
    {
        return parent::rules() + [
            'columns.*' => [new QueryColumn()],
            'relations.*' => ['string'],
        ];
    }

    #[Override]
    final public function parsed(): SelectRequestData
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

        if (property_exists($this, 'columns') && $this->columns !== null && is_string($this->columns)) {
            $to_merge['columns'] = static::decode($this->columns);
        }

        if (property_exists($this, 'relations') && $this->relations !== null && is_string($this->relations)) {
            $to_merge['relations'] = static::decode($this->relations);
        }

        /** @phpstan-ignore method.notFound */
        $this->merge($to_merge);
    }
}
