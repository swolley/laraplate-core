<?php

declare(strict_types=1);

use Modules\Core\Casts\CrudExecutor;

it('returns all crud executor values', function (): void {
    $values = CrudExecutor::values();

    expect($values)->toContain(CrudExecutor::SELECT)
        ->and($values)->toContain(CrudExecutor::COUNT)
        ->and($values)->toContain(CrudExecutor::INSERT)
        ->and($values)->toContain(CrudExecutor::UPDATE)
        ->and($values)->toContain(CrudExecutor::DELETE)
        ->and($values)->toContain(CrudExecutor::FORCE_DELETE)
        ->and($values)->toContain(CrudExecutor::RESTORE);
});

it('returns value from tryFrom when action exists', function (): void {
    expect(CrudExecutor::tryFrom(CrudExecutor::SELECT))->toBe(CrudExecutor::SELECT)
        ->and(CrudExecutor::tryFrom(CrudExecutor::FORCE_DELETE))->toBe(CrudExecutor::FORCE_DELETE);
});

it('returns null from tryFrom when action does not exist', function (): void {
    expect(CrudExecutor::tryFrom('missing-action'))->toBeNull();
});

it('recognizes write and read actions', function (): void {
    expect(CrudExecutor::isWriteAction(CrudExecutor::INSERT))->toBeTrue()
        ->and(CrudExecutor::isWriteAction(CrudExecutor::UPDATE))->toBeTrue()
        ->and(CrudExecutor::isWriteAction(CrudExecutor::DELETE))->toBeTrue()
        ->and(CrudExecutor::isWriteAction(CrudExecutor::FORCE_DELETE))->toBeTrue()
        ->and(CrudExecutor::isWriteAction(CrudExecutor::RESTORE))->toBeTrue()
        ->and(CrudExecutor::isWriteAction(CrudExecutor::SELECT))->toBeFalse()
        ->and(CrudExecutor::isReadAction(CrudExecutor::SELECT))->toBeTrue()
        ->and(CrudExecutor::isReadAction(CrudExecutor::COUNT))->toBeTrue()
        ->and(CrudExecutor::isReadAction(CrudExecutor::UPDATE))->toBeFalse();
});
