<?php

declare(strict_types=1);

use Modules\Core\Overrides\PostgresConnection;

it('is the connection the pgsql driver resolves to', function (): void {
    $connection = app('db.factory')->make([
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'database' => 'laraplate',
        'username' => 'laraplate',
        'password' => '',
    ], 'pgsql_probe');

    expect($connection)->toBeInstanceOf(PostgresConnection::class);
});
