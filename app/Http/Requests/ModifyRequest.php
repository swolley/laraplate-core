<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Support\Str;
use Modules\Core\Casts\IParsableRequest;
use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Helpers\HasValidations;
use Override;

// use Illuminate\Foundation\Http\FormRequest;

final class ModifyRequest extends CrudRequest implements IParsableRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $mergeRules = [];

    #[Override]
    public function rules(): array
    {
        return array_merge(parent::rules(), $this->mergeRules);
    }

    #[Override]
    public function parsed(): ModifyRequestData
    {
        /** @phpstan-ignore method.notFound */
        return new ModifyRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Ensure primary key (e.g. id) from route/query is in request for update/delete so validated() and ModifyRequestData have it
        $pk = is_array($this->primaryKey) ? $this->primaryKey : [$this->primaryKey];

        foreach ($pk as $key) {
            $value = $this->route($key) ?? $this->input($key);

            if ($value !== null && $value !== '') {
                $this->merge([$key => $value]);
            }
        }

        $to_merge = [
            'filters' => $this->filters ?? [],
        ];

        /** @phpstan-ignore method.notFound */
        $is_insert = Str::contains($this->url(), '/insert/');

        /** @phpstan-ignore method.notFound */
        $is_update = Str::contains($this->url(), '/update/');
        $is_autoincrement = $this->model->incrementing;

        // Primary key: required for update/detail/delete; omit from rules on insert with autoincrement so it is not validated
        if (! $is_autoincrement || ! $is_insert) {
            $validation = ['required'];

            if ($this->model->getKeyType() === 'int') {
                $validation[] = 'integer';
                $validation[] = 'numeric';
            }

            foreach (is_array($this->primaryKey) ? $this->primaryKey : [$this->primaryKey] as $key) {
                $to_merge[$key] = $validation;
            }
        }

        // if model has built-in validation rules, add them for validation (do not merge into input)
        /** @phpstan-ignore method.notFound */
        $is_delete = Str::contains($this->url(), '/delete/');

        if (class_uses_trait($this->model, HasValidations::class) && ! $is_delete) {
            /** @phpstan-ignore method.notFound */
            $main_entity = $this->route()->entity;

            $pk_keys = is_array($this->primaryKey) ? $this->primaryKey : [$this->primaryKey];

            foreach ($this->model->getOperationRules($is_insert ? 'create' : ($is_update ? 'update' : null)) as $attribute => $rule) {
                $rule_key = $attribute;

                // For update/delete skip merging model rules for primary key so we don't validate "exists" (service returns 404 when not found)
                if (($is_update || $is_delete) && in_array($rule_key, $pk_keys, true)) {
                    continue;
                }
                $existing = $to_merge[$rule_key] ?? [];
                $normalized = $this->normalizeRules($rule);

                // For update/delete, only validate non-PK fields when present (avoid requiring password/username on partial update)
                if (($is_update || $is_delete) && ! in_array($rule_key, $pk_keys, true)) {
                    $normalized = $this->mergeRulesUnique(['sometimes', ...$normalized]);
                }
                $to_merge[$rule_key] = $this->mergeRulesUnique([...(array) $existing, ...$normalized]);

                $to_merge['filters'][] = ['property' => $rule_key, 'value' => $this->input($rule_key) ?? $this->input(sprintf('%s.%s', $main_entity, $rule_key))];
            }
        }

        $this->mergeRules = $to_merge;
        $this->merge(['filters' => $this->filters ?? []]);
    }

    /**
     * Merge rule arrays and deduplicate only when all elements are scalar (string/int) so that Laravel rule objects (e.g. Password) are preserved.
     *
     * @param  array<int, mixed>  $rules
     * @return array<int, string|object>
     */
    private function mergeRulesUnique(array $rules): array
    {
        $all_scalar = ! array_filter($rules, static fn (mixed $r): bool => ! is_scalar($r));

        return array_values($all_scalar ? array_unique($rules) : $rules);
    }

    /**
     * Normalize rules so that strings containing "|" are split into separate rules (Laravel expects either a single string to split or an array of individual rules).
     *
     * @param  array<int, mixed>|string|mixed  $rule
     * @return array<int, string|object>
     */
    private function normalizeRules(array|string $rule): array
    {
        $rules = is_array($rule) ? $rule : [$rule];
        $result = [];

        foreach ($rules as $r) {
            if (is_string($r) && str_contains($r, '|')) {
                foreach (explode('|', $r) as $part) {
                    $part = mb_trim($part);

                    if ($part !== '') {
                        $result[] = $part;
                    }
                }
            } else {
                $result[] = $r;
            }
        }

        return $result;
    }
}
