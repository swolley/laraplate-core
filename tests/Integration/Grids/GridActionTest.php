<?php

declare(strict_types=1);

use Modules\Core\Casts\ActionEnum;
use Modules\Core\Grids\Casts\GridAction;

it('returns all action values', function (): void {
    $values = GridAction::values();

    expect($values)->toContain(GridAction::Select->value)
        ->and($values)->toContain(GridAction::Insert->value)
        ->and($values)->toContain(GridAction::Export->value);
});

it('recognizes read actions', function (): void {
    expect(GridAction::isReadAction(GridAction::Select))->toBeTrue()
        ->and(GridAction::isReadAction(GridAction::Data->value))->toBeTrue()
        ->and(GridAction::isReadAction(GridAction::Insert))->toBeFalse()
        ->and(GridAction::isReadAction(ActionEnum::Delete->value))->toBeFalse();
});

it('recognizes write actions', function (): void {
    expect(GridAction::isWriteAction(GridAction::Update))->toBeTrue()
        ->and(GridAction::isWriteAction(GridAction::Lock->value))->toBeTrue()
        ->and(GridAction::isWriteAction(GridAction::Options))->toBeFalse();
});
