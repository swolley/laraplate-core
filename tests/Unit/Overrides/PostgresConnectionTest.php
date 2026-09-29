<?php

declare(strict_types=1);

use Modules\Core\Overrides\PostgresConnection;

it('binds booleans as boolean literals when prepares are emulated', function (): void {
    $connection = new PostgresConnection(static fn (): null => null, 'laraplate', '', ['options' => [PDO::ATTR_EMULATE_PREPARES => true]]);

    expect($connection->prepareBindings([true, false, 1, 'x']))->toBe(['true', 'false', 1, 'x']);
});

it('keeps the framework integer booleans with native prepares', function (): void {
    $connection = new PostgresConnection(static fn (): null => null, 'laraplate', '', ['options' => []]);

    expect($connection->prepareBindings([true, false]))->toBe([1, 0]);
});
