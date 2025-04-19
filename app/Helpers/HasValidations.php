<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Casts\CrudExecutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;

/**
 * Trait per aggiungere validazioni ai modelli
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

    private $rules = [
        'create' => [],
        'update' => [],
        // 'always' => [],
    ];

    /**
     * Flag per saltare le validazioni
     */
    private bool $skip_validation = false;

    /**
     * Imposta il flag per saltare le validazioni
     */
    public function setSkipValidation(bool $skip = true): void
    {
        $this->skip_validation = $skip;
    }

    /**
     * Verifica se le validazioni devono essere saltate
     */
    public function shouldSkipValidation(): bool
    {
        return $this->skip_validation;
    }

    protected static function bootHasValidations(): void
    {
        //FIXME: no events before retrieved, so I do the query and then check if the user can read, bit I don't like it
        static::retrieved(function (Model $model): void {
            if (!static::checkUserCanDo($model, 'select')) {
                throw new UnauthorizedException('User cannot select ' . $model->getTable());
            }
        });
        static::creating(function (Model $model): void {
            if (!static::checkUserCanDo($model, 'insert')) {
                throw new UnauthorizedException('User cannot insert ' . $model->getTable());
            }
            if (!$model->shouldSkipValidation()) {
                $model->validateWithRules(CrudExecutor::INSERT);
            }
        });
        static::updating(function (Model $model): void {
            if (!static::checkUserCanDo($model, 'update')) {
                throw new UnauthorizedException('User cannot update ' . $model->getTable());
            }

            if (!$model->isDirty('deleted_at') && !$model->shouldSkipValidation()) {
                $model->validateWithRules(CrudExecutor::UPDATE);
            }
        });
        static::deleting(function (Model $model): void {
            if ((!method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) && !static::checkUserCanDo($model, 'forceDelete')) {
                throw new UnauthorizedException('User cannot delete ' . $model->getTable());
            }

            if (!static::checkUserCanDo($model, 'delete')) {
                throw new UnauthorizedException('User cannot soft delete ' . $model->getTable());
            }
        });

        if (method_exists(static::class, 'forceDeleting')) {
            static::forceDeleting(function (Model $model): void {
                if (!static::checkUserCanDo($model, 'forceDelete')) {
                    throw new UnauthorizedException('User cannot delete ' . $model->getTable());
                }
            });
        }

        if (method_exists(static::class, 'restoring')) {
            static::restoring(function (Model $model): void {
                if (!static::checkUserCanDo($model, 'restore')) {
                    throw new UnauthorizedException('User cannot restore ' . $model->getTable());
                }
            });
        }
    }

    protected static function checkUserCanDo(Model $model, string $operation): bool
    {
        $permission = $model->getTable() . '.' . $operation;
        $permission_class = config('permission.models.permission');

        if (!$permission_class::whereName($permission)->count()) {
            return true;
        }

        if ($user = Auth::user()) {
            /** @var User $user */
            if ($user->isSuperAdmin()) {
                return true;
            }
            return (bool) $user->hasPermission($permission);
        }

        return true;
    }

    public function getRules(): array
    {
        $primary_key = $this->getKeyName();
        $rules = $this->rules;
        if (!isset($rules[static::DEFAULT_RULE])) {
            $rules[static::DEFAULT_RULE] = [];
        }
        $rules['update'] = array_merge($rules['update'], [
            $primary_key => 'required|exists:' . $this->getTable() . ',' . $primary_key,
        ]);
        return $rules;
    }

    public function getOperationRules(?string $operation = null): array
    {
        $rules = $this->getRules();
        return $operation && array_key_exists($operation, $rules) ? array_merge($rules[static::DEFAULT_RULE] ?? [], $rules[$operation]) : $rules[static::DEFAULT_RULE] ?? [];
    }

    public function validateWithRules(string $operation): void
    {
        if ($this->shouldSkipValidation()) {
            return;
        }

        $rules = $this->getOperationRules($operation);

        if (!empty($rules)) {
            Validator::make($this->getAttributes(), $rules)->validate();
        }
    }
}
