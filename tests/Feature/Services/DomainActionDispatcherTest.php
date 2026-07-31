<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DomainActionDispatcher;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\Core\Tests\Stubs\DomainActions\PlainActionModel;

it('invokes the handler with record, payload and user when authorized', function (): void {
    Gate::define('archive', fn (): bool => true);

    $registry = new DomainActionRegistry();
    $registry->register(
        PlainActionModel::class,
        'archive',
        fn (Model $record, array $payload, User $user): array => $payload,
    );

    $result = new DomainActionDispatcher($registry)
        ->dispatch(new PlainActionModel(), 'archive', User::factory()->create(), ['reason' => 'obsolete']);

    expect($result)->toBe(['reason' => 'obsolete']);
});

it('raises a not-found error for an unregistered action', function (): void {
    new DomainActionDispatcher(new DomainActionRegistry())
        ->dispatch(new PlainActionModel(), 'nope', User::factory()->create());
})->throws(ModelNotFoundException::class);

it('refuses when the gate denies the action', function (): void {
    Gate::define('archive', fn (): bool => false);

    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'archive', fn (): null => null);

    new DomainActionDispatcher($registry)
        ->dispatch(new PlainActionModel(), 'archive', User::factory()->create());
})->throws(AuthorizationException::class);

it('maps a snake_case action onto its camelCase policy method', function (): void {
    $seen = null;
    Gate::define('forcePost', function () use (&$seen): bool {
        $seen = 'forcePost';

        return true;
    });

    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'force_post', fn (): null => null);

    new DomainActionDispatcher($registry)
        ->dispatch(new PlainActionModel(), 'force_post', User::factory()->create());

    expect($seen)->toBe('forcePost');
});
