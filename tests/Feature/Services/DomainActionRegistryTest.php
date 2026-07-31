<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\Core\Tests\Stubs\DomainActions\ApprovableActionModel;
use Modules\Core\Tests\Stubs\DomainActions\OverridingActionModel;
use Modules\Core\Tests\Stubs\DomainActions\PlainActionModel;

it('resolves a registered handler for a model and action', function (): void {
    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'archive', fn (Model $record, array $payload, User $user): string => 'archived');

    $handler = $registry->resolve(PlainActionModel::class, 'archive');

    expect($handler)->not->toBeNull()
        ->and($handler(new PlainActionModel(), [], new User()))->toBe('archived');
});

it('returns null for an action that was never registered', function (): void {
    $registry = new DomainActionRegistry();

    expect($registry->resolve(PlainActionModel::class, 'nope'))->toBeNull();
});

it('lists the actions registered for a model', function (): void {
    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'archive', fn (): null => null);
    $registry->register(PlainActionModel::class, 'restore_from_archive', fn (): null => null);

    expect($registry->actionsFor(PlainActionModel::class))
        ->toBe(['archive', 'restore_from_archive']);
});

it('rejects registering the same action twice for one model', function (): void {
    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'archive', fn (): null => null);

    $registry->register(PlainActionModel::class, 'archive', fn (): null => null);
})->throws(LogicException::class, 'already registered');

it('allows registering a generic verb when the model declares the override', function (): void {
    $registry = new DomainActionRegistry();

    $registry->register(OverridingActionModel::class, 'approve', fn (): null => null);

    expect($registry->has(OverridingActionModel::class, 'approve'))->toBeTrue();
});

it('rejects a generic verb the model did not declare as overridden', function (): void {
    $registry = new DomainActionRegistry();

    $registry->register(PlainActionModel::class, 'approve', fn (): null => null);
})->throws(LogicException::class, 'must declare it in overriddenCrudActions()');

it('rejects overriding approve on a model that also uses HasApprovals', function (): void {
    $registry = new DomainActionRegistry();

    $registry->register(ApprovableActionModel::class, 'approve', fn (): null => null);
})->throws(LogicException::class, 'already means');
