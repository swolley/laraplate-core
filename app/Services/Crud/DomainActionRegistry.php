<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Core\Contracts\OverridesGenericCrudActions;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\SoftDeletes\SoftDeletes;

/**
 * Maps {model, action} to a handler.
 *
 * The registry, not the route table, decides which domain actions exist: one
 * generic route serves every module, and a module adds an action by registering
 * it rather than by declaring a route.
 *
 * Handlers have the shape fn (Model $record, array $payload, User $user): mixed.
 * Returning a Symfony Response makes the controller pass it through untouched,
 * which is how file exports reach the client.
 */
final class DomainActionRegistry
{
    /**
     * Core's generic verbs and the trait that gives each one its generic meaning.
     *
     * `cache-clear` maps to null: it is generic for every model with no trait
     * behind it, so a module can never redefine it.
     *
     * @var array<string, ?class-string>
     */
    private const array GENERIC_VERBS = [
        'approve' => HasApprovals::class,
        'disapprove' => HasApprovals::class,
        'lock' => HasLocks::class,
        'unlock' => HasLocks::class,
        'activate' => SoftDeletes::class,
        'inactivate' => SoftDeletes::class,
        'cache-clear' => null,
    ];

    /** @var array<class-string<Model>, array<string, callable>> */
    private array $handlers = [];

    public function register(string $model_class, string $action, callable $handler): void
    {
        $this->assertMayOverride($model_class, $action);

        throw_if(
            isset($this->handlers[$model_class][$action]),
            LogicException::class,
            sprintf('Domain action [%s] is already registered for [%s].', $action, $model_class),
        );

        $this->handlers[$model_class][$action] = $handler;
    }

    public function resolve(string $model_class, string $action): ?callable
    {
        return $this->handlers[$model_class][$action] ?? null;
    }

    public function has(string $model_class, string $action): bool
    {
        return isset($this->handlers[$model_class][$action]);
    }

    /**
     * @return list<string>
     */
    public function actionsFor(string $model_class): array
    {
        return array_keys($this->handlers[$model_class] ?? []);
    }

    /**
     * Modules register at boot, so a contradiction stops the application on
     * start in every environment rather than surfacing the first time one
     * particular record is touched.
     */
    private function assertMayOverride(string $model_class, string $action): void
    {
        if (! array_key_exists($action, self::GENERIC_VERBS)) {
            return;
        }

        $declared = is_a($model_class, OverridesGenericCrudActions::class, true)
            ? $model_class::overriddenCrudActions()
            : [];

        throw_unless(
            in_array($action, $declared, true),
            LogicException::class,
            sprintf(
                '[%s] is a generic CRUD verb; to redefine it for [%s] the model must declare it in overriddenCrudActions().',
                $action,
                $model_class,
            ),
        );

        $trait = self::GENERIC_VERBS[$action];

        throw_if(
            $trait !== null && class_uses_trait($model_class, $trait),
            LogicException::class,
            sprintf(
                '[%s] already means something on [%s] through [%s]; one entity cannot carry both meanings of the same verb.',
                $action,
                $model_class,
                $trait,
            ),
        );
    }
}
