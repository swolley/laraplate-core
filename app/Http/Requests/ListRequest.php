<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Override;
use Modules\Core\Rules\QueryBuilder;
use Modules\Core\Casts\ListRequestData;

class ListRequest extends SelectRequest
{
    /**
     * @return array<string, mixed>
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

        $sort = $this->input('sort');
        if ($sort) {
            $to_merge['sort'] = is_string($sort) && is_json($sort) ? json_decode($sort, true) : (is_string($sort) ? preg_split("/,\s?/", $sort) : $sort);
        }

        $filters = $this->input('filters');
        if ($filters) {
            $to_merge['filters'] = is_string($filters) && is_json($filters) ? json_decode($filters, true) : $filters;
        }

        $group_by = $this->input('group_by');
        if ($group_by) {
            $to_merge['group_by'] = is_string($group_by) && is_json($group_by) ? json_decode($group_by, true) : $group_by;
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
