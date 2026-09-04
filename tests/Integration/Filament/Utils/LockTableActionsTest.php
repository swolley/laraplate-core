<?php

declare(strict_types=1);

use Filament\Actions\ActionGroup;
use Filament\Tables\Contracts\HasTable as HasTableContract;
use Filament\Tables\Table;
use App\Models\User;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Tests\Unit\Filament\Utils\CoreHasTableTraitHarness;

/**
 * Tables offer freeze and unfreeze, never the lease: a lease belongs to the edit lifecycle and is
 * taken by opening the form. The two actions sit behind two different permissions on purpose, since
 * being trusted to block a record and being trusted to unblock other people are not the same job.
 *
 * @return list<string>
 */
function lockTableActionNames(User $actor): array
{
    test()->actingAs($actor);

    $livewire = Mockery::mock(HasTableContract::class)->shouldIgnoreMissing();
    $table = Table::make($livewire)->query(User::query());

    $configure = new ReflectionMethod(CoreHasTableTraitHarness::class, 'configureActions');
    $configure->setAccessible(true);
    $configure->invoke(
        null,
        $table,
        false,   // hasSoftDeletes
        false,   // hasValidity
        false,   // hasSearchable
        false,   // hasActivation
        false,   // hasTranslations
        true,    // hasLocks
        null,    // actions
        [],      // fixedActions
        'default.users',
        $actor,
    );

    // Record actions are wrapped in an ActionGroup, so the names live one level down.
    return collect($table->getRecordActions())
        ->flatMap(static fn (object $action): array => $action instanceof ActionGroup
            ? $action->getActions()
            : [$action])
        ->map(static fn (object $action): string => method_exists($action, 'getName') ? (string) $action->getName() : '')
        ->all();
}

function lockTableOperator(string ...$operations): User
{
    $role = Role::findOrCreate('table_lock_' . uniqid(), 'web');

    foreach ($operations as $operation) {
        $name = 'default.users.' . $operation;
        Permission::findOrCreate($name, 'web');
        $role->givePermissionTo($name);
    }

    $operator = User::factory()->create();
    $operator->assignRole($role);

    return $operator;
}

it('offers freeze only to a holder of the lock permission', function (): void {
    expect(lockTableActionNames(lockTableOperator('lock')))->toContain('freeze')
        ->and(lockTableActionNames(lockTableOperator('update')))->not->toContain('freeze');
});

it('offers unfreeze only to a holder of the unlock permission', function (): void {
    // `lock` deliberately does not carry it: freezing records and releasing other people's blocks
    // are different responsibilities.
    expect(lockTableActionNames(lockTableOperator('unlock')))->toContain('unfreeze')
        ->and(lockTableActionNames(lockTableOperator('lock')))->not->toContain('unfreeze');
});

it('never offers a lease from the table', function (): void {
    $names = lockTableActionNames(lockTableOperator('update', 'lock', 'unlock'));

    expect($names)->not->toContain('lock');
});
