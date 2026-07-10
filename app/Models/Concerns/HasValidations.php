<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Casts\CrudExecutor;
use Modules\Core\Overrides\ContextualValidationException;
use Modules\Core\Overrides\ContextualValidator;

/**
 * Trait per aggiungere validazioni ai modelli.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @method void setSkipValidation(bool $skip = true) Imposta il flag per saltare le validazioni
 * @method bool shouldSkipValidation() Verifica se le validazioni devono essere saltate
 * @method array getRules() Ottiene le regole di validazione
 * @method array getOperationRules(?string $operation = null) Ottiene le regole di validazione per un'operazione specifica
 * @method void validateWithRules(string $operation) Valida il modello con le regole
 *
 * @phpstan-type HasValidationsType HasValidations
 */
trait HasValidations
{
    public const DEFAULT_RULE = 'always';

    protected $rules = [
        'create' => [],
        'update' => [],
        // 'always' => [],
    ];

    /**
     * Flag per saltare le validazioni.
     */
    protected bool $skip_validation = false;

    /**
     * In-memory cache for permission existence checks.
     * Keyed by permission name, value is whether the permission row exists in DB.
     * Populated on first access; reset between requests naturally by PHP-FPM.
     *
     * @var array<string, bool>
     */
    private static array $permission_existence_cache = [];

    /**
     * Reset the in-memory permission existence cache.
     * Used in tests and long-running processes to clear stale state.
     */
    public static function resetPermissionExistenceCache(): void
    {
        self::$permission_existence_cache = [];
    }

    /**
     * Imposta il flag per saltare le validazioni.
     */
    public function setSkipValidation(bool $skip = true): void
    {
        $this->skip_validation = $skip;
    }

    /**
     * Verifica se le validazioni devono essere saltate.
     */
    public function shouldSkipValidation(): bool
    {
        return $this->skip_validation;
    }

    /**
     * Returns the model attributes used for validation.
     * Can be overridden in concrete classes or other traits (e.g. HasPlace)
     * to include additional virtual attributes not stored directly on the model.
     *
     * @return array<string, mixed>
     */
    public function getAttributesForValidation(): array
    {
        $attributes = $this->getAttributes();

        foreach ($this->getCasts() as $key => $cast) {
            if (! array_key_exists($key, $attributes) || ! $this->shouldUseCastedAttributeForValidation($cast)) {
                continue;
            }

            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }

    /**
     * Whether a casted attribute should be passed to the validator using its cast value.
     */
    protected function shouldUseCastedAttributeForValidation(mixed $cast): bool
    {
        if (! is_string($cast)) {
            return false;
        }

        return in_array($cast, ['array', 'object', 'collection', 'encrypted:array'], true)
            || str_starts_with($cast, 'array:');
    }

    public function getRules(): array
    {
        $primary_key = $this->getKeyName();
        $rules = $this->rules;

        if (! isset($rules[self::DEFAULT_RULE])) {
            $rules[self::DEFAULT_RULE] = [];
        }

        $rules['update'] = array_merge($rules['update'], [
            $primary_key => 'required|exists:' . $this->getTable() . ',' . $primary_key,
        ]);

        return $rules;
    }

    public function getOperationRules(?string $operation = null): array
    {
        $rules = $this->getRules();
        $operation = $this->normalizeValidationOperation($operation);

        return $operation && array_key_exists($operation, $rules) ? array_merge($rules[self::DEFAULT_RULE] ?? [], $rules[$operation]) : $rules[self::DEFAULT_RULE] ?? [];
    }

    /**
     * Maps legacy operation aliases to the rule-set keys used by models.
     */
    protected function normalizeValidationOperation(?string $operation): ?string
    {
        return match ($operation) {
            'save' => 'update',
            default => $operation,
        };
    }

    public function validateWithRules(string $operation): void
    {
        if ($this->shouldSkipValidation()) {
            return;
        }

        $rules = $this->getOperationRules($operation);

        if ($rules !== []) {
            $attributes = $this->getAttributesForValidation();
            $attributes = $this->prepareJsonRuleAttributes($attributes, $rules);

            if (class_uses_trait($this, HasDynamicContents::class)) {
                /** @phpstan-ignore method.notFound */
                $components = $this->getComponentsAttribute();
                $components = $this->prepareJsonRuleAttributes($components, $rules);

                $attributes = array_merge($attributes, $components);

                if (isset($this->attributes['shared_components'])) {
                    $shared = json_decode((string) $this->attributes['shared_components'], true);
                    $attributes = array_merge($attributes, $shared ?? []);
                }
            }

            $validator = Validator::make($attributes, $rules);

            if ($validator instanceof ContextualValidator) {
                $validator->withLogContext([
                    'entity' => $this->getTable(),
                    'model' => static::class,
                    'operation' => $operation,
                    'id' => $this->getKey(),
                ]);
            }

            $validator->setException(ContextualValidationException::class);
            $validator->validate();
        }
    }

    /**
     * Laravel's `json` rule only accepts JSON strings, not PHP arrays or objects.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function prepareJsonRuleAttributes(array $attributes, array $rules): array
    {
        foreach ($attributes as $key => $value) {
            if (! isset($rules[$key]) || ! $this->validationRulesContainJson($rules[$key])) {
                continue;
            }

            if (! is_array($value) && ! is_object($value)) {
                continue;
            }

            $attributes[$key] = json_encode($value);
        }

        return $attributes;
    }

    protected function validationRulesContainJson(mixed $rules): bool
    {
        if (is_string($rules)) {
            return str_contains($rules, 'json');
        }

        if (! is_array($rules)) {
            return false;
        }

        foreach ($rules as $rule) {
            if (is_string($rule) && $rule === 'json') {
                return true;
            }
        }

        return false;
    }

    protected static function bootHasValidations(): void
    {
        // FIXME: no events before retrieved, so I do the query and then check if the user can read, bit I don't like it
        static::retrieved(function (Model $model): void {
            throw_unless(static::checkUserCanDo($model, 'select'), AuthorizationException::class, 'User cannot select ' . $model->getTable());
        });
        static::creating(function (Model $model): void {
            throw_unless(static::checkUserCanDo($model, 'insert'), AuthorizationException::class, 'User cannot insert ' . $model->getTable());

            if (! $model->shouldSkipValidation()) {
                $model->validateWithRules(CrudExecutor::INSERT);
            }
        });
        static::updating(function (Model $model): void {
            throw_unless(static::checkUserCanDo($model, 'update'), AuthorizationException::class, 'User cannot update ' . $model->getTable());

            if (! $model->isDirty('deleted_at') && ! $model->shouldSkipValidation()) {
                $model->validateWithRules(CrudExecutor::UPDATE);
            }
        });
        static::deleting(function (Model $model): void {
            throw_if((! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) && ! static::checkUserCanDo($model, 'forceDelete'), AuthorizationException::class, 'User cannot delete ' . $model->getTable());

            throw_unless(static::checkUserCanDo($model, 'delete'), AuthorizationException::class, 'User cannot soft delete ' . $model->getTable());
        });

        if (method_exists(static::class, 'forceDeleting')) {
            static::forceDeleting(function (Model $model): void {
                throw_unless(static::checkUserCanDo($model, 'forceDelete'), AuthorizationException::class, 'User cannot delete ' . $model->getTable());
            });
        }

        if (method_exists(static::class, 'restoring')) {
            static::restoring(function (Model $model): void {
                throw_unless(static::checkUserCanDo($model, 'restore'), AuthorizationException::class, 'User cannot restore ' . $model->getTable());
            });
        }
    }

    protected static function checkUserCanDo(Model $model, string $operation): bool
    {
        $permission = $model->getTable() . '.' . $operation;
        $permission_class = config('permission.models.permission');

        // L1: check static in-memory cache first to avoid repeated DB queries
        // for the same permission name within the same request lifecycle
        if (! array_key_exists($permission, self::$permission_existence_cache)) {
            self::$permission_existence_cache[$permission] = (bool) $permission_class::whereName($permission)->count();
        }

        if (! self::$permission_existence_cache[$permission]) {
            return true;
        }

        $user = Auth::user();

        if ($user) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return (bool) $user->hasPermission($permission);
        }

        return true;
    }
}
