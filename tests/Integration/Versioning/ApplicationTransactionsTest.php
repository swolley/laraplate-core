<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Versioning\ApplicationTransactions;

/**
 * The transaction `RefreshDatabase` wraps around each test is not one the application opened: counting it would
 * make every version set of the suite look atomic (D52).
 */
afterEach(function (): void {
    DB::purge('application_transactions_other');
});

it('sees no application transaction in a test wrapped by RefreshDatabase', function (): void {
    expect(DB::connection()->transactionLevel())->toBeGreaterThan(0)
        ->and(ApplicationTransactions::isOpenOn(DB::connection()))->toBeFalse();
});

it('sees the transaction the application opened', function (): void {
    DB::connection()->transaction(function (): void {
        expect(ApplicationTransactions::isOpenOn(DB::connection()))->toBeTrue();
    });

    expect(ApplicationTransactions::isOpenOn(DB::connection()))->toBeFalse();
});

it('sees it only on the connection that opened it', function (): void {
    config()->set('database.connections.application_transactions_other', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $other = DB::connection('application_transactions_other');

    DB::connection()->transaction(function () use ($other): void {
        expect(ApplicationTransactions::isOpenOn($other))->toBeFalse();
    });
});
