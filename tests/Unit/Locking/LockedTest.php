<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Locked;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

final class PlainLockModel extends Model
{
    protected $table = 'plain_lock_models';
}

final class TraitLockModel extends Model
{
    use HasLocks;

    protected $table = 'trait_lock_models';
}

it('reads locking configuration values from config repository', function (): void {
    config()->set('core.locking.lock_at_column', 'la');
    config()->set('core.locking.lock_by_column', 'lb');
    config()->set('core.locking.unlock_allowed', false);
    config()->set('core.locking.can_be_unlocked', [PlainLockModel::class]);
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);
    config()->set('core.locking.prevent_notifications_to_locked_objects', true);

    $locked = new Locked();

    expect($locked->lockedAtColumn())->toBe('la')
        ->and($locked->lockedByColumn())->toBe('lb')
        ->and($locked->unlockAllowed())->toBeFalse()
        ->and($locked->classesThatCanBeUnlocked())->toBe([PlainLockModel::class])
        ->and($locked->preventsModificationsOnLockedObjects())->toBeTrue()
        ->and($locked->allowsModificationsOnLockedObjects())->toBeFalse()
        ->and($locked->allowsNotificationsToLockedObjects())->toBeTrue();
});

it('evaluates lock policy and trait usage helpers', function (): void {
    config()->set('core.locking.unlock_allowed', false);
    config()->set('core.locking.can_be_unlocked', [PlainLockModel::class]);

    $locked = new Locked();
    $plain = new PlainLockModel();
    $with_trait = new TraitLockModel();

    expect($locked->canBeUnlocked($plain))->toBeTrue()
        ->and($locked->cannotBeUnlocked($with_trait))->toBeTrue()
        ->and($locked->usesHasLocks($with_trait))->toBeTrue()
        ->and($locked->doesNotUseHasLocks($plain))->toBeTrue();
});

it('returns fallback defaults when config is not bound in container', function (): void {
    $original = Container::getInstance();
    $isolated = new Container();
    Container::setInstance($isolated);

    try {
        $locked = new Locked();

        expect($locked->lockedAtColumn())->toBe('locked_at')
            ->and($locked->lockedByColumn())->toBe('locked_user_id')
            ->and($locked->unlockAllowed())->toBeTrue()
            ->and($locked->classesThatCanBeUnlocked())->toBe([])
            ->and($locked->preventsModificationsOnLockedObjects())->toBeFalse()
            ->and($locked->allowsNotificationsToLockedObjects())->toBeFalse();
    } finally {
        Container::setInstance($original);
    }
});

it('returns fallback defaults when config repository throws errors', function (): void {
    $app = app();
    $original = $app['config'];

    $app->instance('config', new class
    {
        public function get(string $key, mixed $default = null): mixed
        {
            throw new RuntimeException('config failure');
        }
    });

    try {
        $locked = new Locked();
        expect($locked->lockedAtColumn())->toBe('locked_at')
            ->and($locked->lockedByColumn())->toBe('locked_user_id')
            ->and($locked->unlockAllowed())->toBeTrue()
            ->and($locked->classesThatCanBeUnlocked())->toBe([])
            ->and($locked->preventsModificationsOnLockedObjects())->toBeFalse()
            ->and($locked->allowsNotificationsToLockedObjects())->toBeFalse();
    } finally {
        $app->instance('config', $original);
    }
});
