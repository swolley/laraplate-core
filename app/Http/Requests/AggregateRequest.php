<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Exceptions\AggregateException;
use Modules\Core\Services\Crud\Aggregate\AggregateLimits;
use Modules\Core\Services\Crud\Aggregate\AggregateQuery;
use Modules\Core\Support\BooleanInput;
use Override;

/**
 * Standalone aggregate request.
 *
 * Extends {@see ListRequest} to reuse the list vocabulary (`filters`, relations, columns) without touching it.
 * Three names of the aggregate mean something else in that vocabulary: `group_by` is a list of columns,
 * `sort` a list of `{property, direction}` and `limit` a row count. The aggregate owns them here, so their
 * list rules are replaced, and {@see self::parsed()} hands the list data only what the list understands.
 *
 * The rules check the shape of what arrives, and {@see AggregateQuery} the contract: a function or a bucket
 * the contract does not know is refused there, with its own code, not here as a plain shape error. Every
 * failure of the validation is an {@see AggregateException}. That is not every error the request can raise:
 * {@see self::parsed()} builds a {@see ListRequestData}, which can still throw a ValidationException (a
 * malformed scope filter) outside {@see self::failedValidation()}, and the caller has to map it.
 */
final class AggregateRequest extends ListRequest
{
    /**
     * The keys of the aggregate that never reach {@see ListRequestData}.
     */
    private const array AGGREGATE_KEYS = ['measures', 'group_by', 'sort', 'period', 'limit', 'other', 'compare'];

    /**
     * The keys a GET caller sends as a JSON string and that {@see ListRequest} does not decode: it decodes
     * `sort` and `group_by` itself, with the other keys of its vocabulary.
     */
    private const array JSON_KEYS = ['measures', 'period'];

    /**
     * The keys a GET caller sends as text and that the contract reads as true or false.
     */
    private const array FLAG_KEYS = ['other', 'compare'];

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['group_by.*'], $rules['sort.*.property'], $rules['sort.*.direction'], $rules['limit']);

        return [
            ...$rules,
            'measures' => ['required', 'array', 'list', 'min:1', 'max:' . AggregateLimits::fromConfig()->maxMeasures],
            'measures.*' => ['array'],
            'measures.*.fn' => ['required', 'string'],
            'measures.*.field' => ['nullable', 'string'],
            'group_by' => ['sometimes', 'nullable', 'array', self::objectRule('{field, bucket?, label?}')],
            'group_by.field' => ['required_with:group_by', 'string'],
            'group_by.bucket' => ['nullable', 'string'],
            'group_by.label' => ['nullable', 'string'],
            'period' => ['sometimes', 'nullable', 'array'],
            'sort' => ['sometimes', 'nullable', 'array', self::objectRule('{by, dir}')],
            'sort.by' => ['string'],
            'sort.dir' => ['string'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'other' => ['sometimes', 'nullable', 'boolean'],
            'compare' => ['sometimes', 'nullable'],
        ];
    }

    /**
     * The aggregate request as a value, with the limits the configuration sets.
     *
     * @throws AggregateException
     */
    public function aggregateQuery(): AggregateQuery
    {
        return AggregateQuery::fromArray($this->validatedInput(), AggregateLimits::fromConfig());
    }

    /**
     * The list data of the request, built without the keys of the aggregate: `group_by`, `sort` and `limit`
     * would be read as a list reads them.
     */
    #[Override]
    public function parsed(): ListRequestData
    {
        return new ListRequestData($this, $this->resolveMainEntity(), Arr::except($this->validatedInput(), self::AGGREGATE_KEYS), $this->primaryKey, $this->resolveModule());
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $to_merge = [];

        foreach (self::JSON_KEYS as $key) {
            $value = $this->input($key);

            if (is_string($value) && is_json($value)) {
                $to_merge[$key] = json_decode($value, true);
            }
        }

        foreach (self::FLAG_KEYS as $key) {
            if ($this->exists($key)) {
                $to_merge[$key] = BooleanInput::coerce($this->input($key));
            }
        }

        $this->merge($to_merge);
    }

    /**
     * @throws AggregateException With `INVALID_FIELD`, the first message of the validator and the first key that failed.
     */
    #[Override]
    protected function failedValidation(Validator $validator): never
    {
        $errors = $validator->errors();
        $keys = $errors->keys();

        throw new AggregateException(AggregateErrorCode::InvalidField, $errors->first(), isset($keys[0]) ? (string) $keys[0] : null);
    }

    /**
     * A rule that refuses a list where the aggregate wants an object. The list vocabulary has a `group_by` and a
     * `sort` that are lists (and the list request splits a text such as `sort=key` into one), and the framework
     * keeps of an array only the keys that have a rule of their own, so a list would be dropped without a word
     * and the request would run with the default.
     *
     * @return Closure(string, mixed, Closure): void
     */
    private static function objectRule(string $shape): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($shape): void {
            if (is_array($value) && $value !== [] && array_is_list($value)) {
                $fail(sprintf('The %s field must be an object %s.', $attribute, $shape));
            }
        };
    }

    /**
     * Everything that passed the rules. The framework types `validated()` as `mixed` because it takes a key; the
     * validator it reads from answers with the array.
     *
     * @return array<array-key, mixed>
     */
    private function validatedInput(): array
    {
        return $this->validator->validated();
    }
}
