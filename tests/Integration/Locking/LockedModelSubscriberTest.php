<?php

declare(strict_types=1);

/**
 * LockedModelSubscriber tests.
 *
 * Do not assert ReflectionClass::isFinal(): tests/Pest.php enables DG\BypassFinals,
 * which reports final classes as non-final so Mockery can replace methods.
 */
use Illuminate\Support\Facades\Auth;
use Modules\Core\Locking\Exceptions\LockedModelException;
use Modules\Core\Locking\Locked;
use Modules\Core\Locking\LockedModelSubscriber;
use Modules\Core\Models\User;

test('subscriber has correct class structure', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);

    expect($reflection->getName())->toBe('Modules\Core\Locking\LockedModelSubscriber');
    expect($reflection->hasMethod('subscribe'))->toBeTrue();
    expect($reflection->hasMethod('saving'))->toBeTrue();
    expect($reflection->hasMethod('deleting'))->toBeTrue();
    expect($reflection->hasMethod('replicating'))->toBeTrue();
    expect($reflection->hasMethod('notificationSending'))->toBeTrue();
});

test('subscriber subscribe method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'subscribe');

    expect($reflection->getNumberOfParameters())->toBe(0);
    expect($reflection->getReturnType()->getName())->toBe('array');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber saving method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'saving');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('bool');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber deleting method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'deleting');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('bool');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber replicating method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'replicating');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('bool');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber notificationSending method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'notificationSending');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('bool');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber uses correct imports', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use Illuminate\Database\Eloquent\Model;');
    expect($source)->toContain('use Illuminate\Notifications\Events\NotificationSending;');
    expect($source)->toContain('use Modules\Core\Locking\Exceptions\LockedModelException;');
});

test('subscriber subscribe method returns correct event mappings', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('return [');
    expect($source)->toContain('\'eloquent.saving: *\' => \'saving\'');
    expect($source)->toContain('\'eloquent.deleting: *\' => \'deleting\'');
    expect($source)->toContain('\'eloquent.replicating: *\' => \'replicating\'');
    expect($source)->toContain('NotificationSending::class => \'notificationSending\'');
});

test('subscriber saving method handles locked model logic', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('new Locked()->allowsModificationsOnLockedObjects()');
    expect($source)->toContain('new Locked()->doesNotUseHasLocks($model)');
    expect($source)->toContain('$model->wasUnlocked()');
    expect($source)->toContain('$model->wasLocked()');
    expect($source)->toContain('$model->isDirty()');
});

/**
 * The two cases below used to assert the literal source of the guard, which proved only that
 * nobody had reformatted it. What matters is who the guard stops: a lease exists so that one person
 * can work on a record undisturbed, so blocking that person is the one outcome it must never
 * produce, and until this work it did exactly that, because it asked whether the record was locked
 * and never whose lock it was.
 */
test('the saving guard blocks an edit on a record held by somebody else', function (): void {
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);

    $owner = User::factory()->create();
    $actor = User::factory()->create();
    Auth::login($actor);

    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    $reloaded = User::query()->findOrFail($target->getKey());
    $reloaded->name = 'edited by an intruder';

    expect(fn (): bool => $reloaded->save())->toThrow(LockedModelException::class);
});

test('the saving guard lets the holder of the lease edit the record', function (): void {
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);

    $actor = User::factory()->create();
    Auth::login($actor);

    $target = User::factory()->create();
    $target->lockBy($actor, now()->addHour());

    $reloaded = User::query()->findOrFail($target->getKey());
    $reloaded->name = 'edited by the holder';
    $reloaded->save();

    expect($reloaded->fresh()?->name)->toBe('edited by the holder');
});

test('the deleting guard blocks a frozen record and spares the lease holder', function (): void {
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);

    $actor = User::factory()->create();
    Auth::login($actor);

    $frozen = User::factory()->create();
    $frozen->lock();

    $mine = User::factory()->create();
    $mine->lockBy($actor, now()->addHour());

    expect(fn (): ?bool => User::query()->findOrFail($frozen->getKey())->delete())
        ->toThrow(LockedModelException::class);

    User::query()->findOrFail($mine->getKey())->delete();

    expect(User::query()->find($mine->getKey()))->toBeNull();
});

test('subscriber replicating method handles locked model logic', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$model->isUnlocked()');
    expect($source)->toContain('throw new LockedModelException(\'This model is locked\')');
});

test('subscriber notificationSending method handles locked model logic', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$locked->allowsNotificationsToLockedObjects()');
    expect($source)->toContain('$model->isUnlocked()');
    expect($source)->toContain('throw new LockedModelException(\'This model is locked\')');
});

test('subscriber has private helper method', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);

    expect($reflection->hasMethod('getModelFromPassedParams'))->toBeTrue();

    $helperMethod = new ReflectionMethod(LockedModelSubscriber::class, 'getModelFromPassedParams');
    expect($helperMethod->isPrivate())->toBeTrue();
    expect($helperMethod->getNumberOfParameters())->toBe(1);
});

test('subscriber helper method handles array parameters', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('if (is_array($params) && $params !== [] && $params[0] instanceof Model)');
    expect($source)->toContain('return $params[0];');
    expect($source)->toContain('return null;');
});

test('a system write says out loud that it is bypassing the lease', function (): void {
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);

    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    // No authenticated user, as on a queue or in the console. The lease is respected by default,
    // which is the point: a background job must not quietly overwrite somebody's work in progress.
    expect(fn (): bool => User::query()->findOrFail($target->getKey())->forceFill(['name' => 'job'])->save())
        ->toThrow(LockedModelException::class);

    $result = Locked::withoutGuard(function () use ($target): string {
        $record = User::query()->findOrFail($target->getKey());
        $record->name = 'written by a system task';
        $record->save();

        return 'done';
    });

    expect($result)->toBe('done')
        ->and($target->fresh()?->name)->toBe('written by a system task')
        // Still locked: the bypass writes through the guard, it does not release the lock.
        ->and($target->fresh()?->isLockedBy($owner))->toBeTrue();
});

test('the guard is back on after the bypass, including when it throws', function (): void {
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);

    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    expect(fn (): mixed => Locked::withoutGuard(function (): never {
        throw new RuntimeException('the system task failed');
    }))->toThrow(RuntimeException::class);

    expect(Locked::guardIsSuspended())->toBeFalse()
        ->and(fn (): bool => User::query()->findOrFail($target->getKey())->forceFill(['name' => 'after'])->save())
        ->toThrow(LockedModelException::class);
});

test('a nested bypass does not switch the guard back on halfway through', function (): void {
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);

    $inner_saw_suspended = Locked::withoutGuard(fn (): bool => Locked::withoutGuard(
        fn (): bool => Locked::guardIsSuspended(),
    ) && Locked::guardIsSuspended());

    expect($inner_saw_suspended)->toBeTrue()
        ->and(Locked::guardIsSuspended())->toBeFalse();
});
