<?php

declare(strict_types=1);

use Modules\Core\Casts\ActionEnum;

it('has select as read action', function (): void {
    expect(ActionEnum::isReadAction('select'))->toBeTrue();
});

it('has insert as write action', function (): void {
    expect(ActionEnum::isWriteAction('insert'))->toBeTrue();
});

it('isWriteAction returns false for select', function (): void {
    expect(ActionEnum::isWriteAction('select'))->toBeFalse();
});

it('isReadAction returns false for update', function (): void {
    expect(ActionEnum::isReadAction('update'))->toBeFalse();
});

it('has expected cases', function (): void {
    expect(ActionEnum::Select->value)->toBe('select')
        ->and(ActionEnum::Insert->value)->toBe('insert')
        ->and(ActionEnum::Delete->value)->toBe('delete');
});
