<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Exceptions\ScopeFilterException;
use Modules\Core\Support\ScopeGuard;
use Override;

/**
 * Checks the scope nodes of a CRUD request's filters against the requested model, before the request filters are
 * merged with the caller's ACL ({@see ScopeGuard::assertClientFilterable()}), so a refusal is a 422 naming the node's
 * path, the scope and the reason, never the model class nor a parameter value. The parser checks every node again
 * ({@see \Modules\Core\Casts\ListRequestData}), which is the gate for callers that build the request data without
 * this validation.
 *
 * The shape of the filters is {@see QueryBuilder}'s concern: a malformed scope node is reported there and skipped
 * here.
 */
final readonly class ClientScopeFilters implements ValidationRule
{
    /**
     * @param  Model|null  $model  The requested model; null when the entity cannot be resolved
     */
    public function __construct(private ?Model $model) {}

    /**
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        if (Arr::isList($value)) {
            foreach ($value as $index => $node) {
                $this->validate(sprintf('%s.%s', $attribute, $index), $node, $fail);
            }

            return;
        }

        if (array_key_exists('scope', $value)) {
            $this->validateScope($attribute, $value, $fail);

            return;
        }

        if (array_key_exists('filters', $value)) {
            $this->validate($attribute . '.filters', $value['filters'], $fail);
        }
    }

    /**
     * @param  array<mixed>  $node
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function validateScope(string $attribute, array $node, Closure $fail): void
    {
        $scope = $node['scope'];
        $parameters = $node['parameters'] ?? [];

        if (! is_string($scope) || $scope === '' || ! is_array($parameters) || ! Arr::isList($parameters)) {
            return;
        }

        if (! $this->model instanceof Model) {
            $fail(sprintf('%s scope "%s" cannot be checked: the entity cannot be resolved', $attribute, $scope));

            return;
        }

        try {
            ScopeGuard::assertClientFilterable($this->model, new ScopeFilter($scope, $parameters));
        } catch (ScopeFilterException $exception) {
            $fail($attribute . ' ' . $exception->getMessage());
        }
    }
}
