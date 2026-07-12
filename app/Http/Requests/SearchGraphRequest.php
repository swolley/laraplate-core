<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Validation\Validator;
use Modules\Core\Casts\SearchGraphRequestData;
use Override;

class SearchGraphRequest extends SearchRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    #[Override]
    public function rules(): array
    {
        $maxDepth = (int) config('graph.max_depth', 3);
        $maxRelationLimit = (int) config('graph.max_relation_limit', 100);

        return parent::rules() + [
            'relations' => ['sometimes', 'array'],
            'relations.*' => ['string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/'],
            'depth' => ['sometimes', 'integer', 'min:1', 'max:' . $maxDepth],
            'relation_limit' => ['sometimes', 'integer', 'min:1', 'max:' . $maxRelationLimit],
            'node_detail' => ['sometimes', 'string', 'in:minimal,summary,full'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $depth = (int) $this->input('depth', 0);

                if ($depth === 0) {
                    return;
                }

                foreach ($this->input('relations', []) as $relation) {
                    if (is_string($relation) && substr_count($relation, '.') + 1 > $depth) {
                        $validator->errors()->add('relations', 'Relation paths cannot be deeper than depth.');
                    }
                }
            },
        ];
    }

    #[Override]
    public function parsed(): SearchGraphRequestData
    {
        return new SearchGraphRequestData($this, $this->resolveMainEntity(), $this->validated(), $this->primaryKey, $this->input('module'));
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'relations' => $this->normalizeRelations($this->input('relations', [])),
        ]);
    }

    /**
     * @return list<string>
     */
    private function normalizeRelations(mixed $relations): array
    {
        if (is_string($relations)) {
            $relations = is_json($relations) ? json_decode($relations, true) : preg_split('/,\s?/', $relations);
        }

        if (! is_array($relations)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $relation): ?string {
            if (is_string($relation)) {
                return $relation;
            }

            if (is_array($relation) && isset($relation['name']) && is_string($relation['name'])) {
                return $relation['name'];
            }

            return null;
        }, $relations)));
    }
}
