<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Override;
use Modules\Core\Rules\QueryBuilder;
use Modules\Core\Casts\ListRequestData;

final class ListRequest extends SelectRequest
{
    /**
     * @return mixed[]
     */
    #[Override]
    public function rules(): array
    {
        $rules = parent::rules() + [
            'pagination' => ['integer', 'numeric', 'min:1', 'exclude_if:count,true'],
            'page' => ['integer', 'numeric', 'min:1', 'exclude_if:count,true'],
            'from' => ['integer', 'numeric', 'min:1', 'exclude_if:count,true'],
            'to' => ['integer', 'numeric', 'min:1', 'exclude_if:count,true'],
            'limit' => ['integer', 'numeric', 'min:1', 'exclude_if:count,true'],
            'count' => ['boolean'],
            'sort.*.property' => ['string'],
            'sort.*.direction' => ['in:asc,desc,ASC,DESC'],
            'filters' => [new QueryBuilder()],
            'group_by.*' => ['string'],
        ];
        $rules['relations.*'][] = 'exclude_if:count,true';

        return $rules;
    }

    #[Override]
    public function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $to_merge = [];

        if (property_exists($this, 'sort') && $this->sort !== null) {
            $to_merge['sort'] = is_string($this->sort) && is_json($this->sort) ? json_decode($this->sort, true) : (is_string($this->sort) ? preg_split("/,\s?/", $this->sort) : $this->sort);
        }

        if (property_exists($this, 'filters') && $this->filters !== null) {
            $to_merge['filters'] = is_string($this->filters) && is_json($this->filters) ? json_decode($this->filters, true) : $this->filters;
        }

        if (property_exists($this, 'group_by') && $this->group_by !== null) {
            $to_merge['group_by'] = is_string($this->group_by) && is_json($this->group_by) ? json_decode($this->group_by, true) : $this->group_by;
        }

        /** @phpstan-ignore method.notFound */
        $this->merge($to_merge);
    }

    #[Override]
    public function parsed(): ListRequestData
    {
        /** @phpstan-ignore method.notFound */
        return new ListRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
