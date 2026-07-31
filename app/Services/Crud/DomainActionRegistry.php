<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud;

use Illuminate\Database\Eloquent\Model;
use LogicException;

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
    /** @var array<class-string<Model>, array<string, callable>> */
    private array $handlers = [];

    public function register(string $model_class, string $action, callable $handler): void
    {
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
}
