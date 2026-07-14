<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Casts\SearchMode;
use Modules\Core\Casts\SearchRequestData;
use Modules\Core\Search\Enums\TextMatchPreference;
use Override;

/**
 * @property string $qs
 */
class SearchRequest extends ListRequest
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
            'matching' => ['sometimes', Rule::enum(TextMatchPreference::class)],
            'matching_options' => ['sometimes', 'array'],
            'matching_options.max_edits' => ['sometimes', 'integer', 'between:0,1'],
            'matching_options.prefix' => ['sometimes', 'boolean'],
            'matching_options.operator' => ['sometimes', Rule::in(['and', 'or'])],
            'matching_options.minimum_should_match' => ['sometimes', 'integer', 'between:1,100'],
            'matching_options.identifier_typos' => ['sometimes', 'boolean'],
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

        $to_merge = [
            'mode' => $this->input('mode') ?? SearchMode::Auto->value,
            'matching' => $this->input('matching') ?? TextMatchPreference::Auto->value,
        ];

        /** @phpstan-ignore method.notFound */
        $this->merge($to_merge);
    }
}
