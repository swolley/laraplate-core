<?php

declare(strict_types=1);

use Modules\Core\Casts\ActionEnum;
use Modules\Core\Grids\Casts\GridAction;

it('returns all action values', function (): void {
    $values = GridAction::values();

    expect($values)->toContain(GridAction::SELECT->value)
        ->and($values)->toContain(GridAction::INSERT->value)
        ->and($values)->toContain(GridAction::EXPORT->value);
});

it('recognizes read actions', function (): void {
    expect(GridAction::isReadAction(GridAction::SELECT))->toBeTrue()
        ->and(GridAction::isReadAction(GridAction::DATA->value))->toBeTrue()
        ->and(GridAction::isReadAction(GridAction::INSERT))->toBeFalse()
        ->and(GridAction::isReadAction(ActionEnum::DELETE->value))->toBeFalse();
});

it('recognizes write actions', function (): void {
    expect(GridAction::isWriteAction(GridAction::UPDATE))->toBeTrue()
        ->and(GridAction::isWriteAction(GridAction::LOCK->value))->toBeTrue()
        ->and(GridAction::isWriteAction(GridAction::OPTIONS))->toBeFalse();
});
