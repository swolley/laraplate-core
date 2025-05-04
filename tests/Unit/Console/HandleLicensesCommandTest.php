<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Modules\Core\Console\HandleLicensesCommand;

it('can list licenses', function (): void {
    /** @var DatabaseManager&Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class)->makePartial();
    $command = new HandleLicensesCommand($db);

    expect(function () use ($command): void {
        $command->listLicenses();
    })->not->toThrow();
});

it('can renew licenses', function (): void {
    /** @var DatabaseManager&Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class)->makePartial();
    $command = new HandleLicensesCommand($db);

    expect(function () use ($command): void {
        $command->renewLicenses(2, 5, now()->addDays(30));
    })->not->toThrow();
});

it('can add licenses', function (): void {
    /** @var DatabaseManager&Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class)->makePartial();
    $command = new HandleLicensesCommand($db);

    expect(function () use ($command): void {
        $command->addLicenses(3, now()->addDays(30));
    })->not->toThrow();
});

it('can close licenses', function (): void {
    /** @var DatabaseManager&Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class)->makePartial();
    $command = new HandleLicensesCommand($db);

    expect(function () use ($command): void {
        $command->closeLicenses(4, now()->addDays(30));
    })->not->toThrow();
});
