<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Casts\SearchMode;
use Modules\Core\Casts\SearchRequestData;
use Override;

/**
 * @property string $qs
 */
final class SearchRequest extends ListRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    #[Override]
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['count']);

        foreach (array_keys($rules) as $rule) {
            if (Str::contains($rule, ['sort.', 'group_by.', 'relations.'])) {
                unset($rules[$rule]);
            }
        }

        return $rules + [
            'qs' => ['string', 'required'],
            'mode' => ['sometimes', Rule::enum(SearchMode::class)],
        ];
    }

    #[Override]
    public function parsed(): SearchRequestData
    {
        /** @phpstan-ignore method.notFound */
        return new SearchRequestData($this, $this->resolveMainEntity(), $this->validated(), $this->primaryKey, $this->input('module'));
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $to_merge = ['mode' => $this->input('mode') ?? SearchMode::Auto->value];

        /** @phpstan-ignore method.notFound */
        $this->merge($to_merge);
    }
}
