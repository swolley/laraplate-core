<?php

use Modules\Core\Console\HandleLicensesCommand;
use Illuminate\Database\DatabaseManager;

it('can list licenses', function () {
    /** @var DatabaseManager&\Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class)->makePartial();
    $command = new HandleLicensesCommand($db);

    expect(function () use ($command) {
        $command->listLicenses();
    })->not->toThrow();
});

it('can renew licenses', function () {
    /** @var DatabaseManager&\Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class)->makePartial();
    $command = new HandleLicensesCommand($db);

    expect(function () use ($command) {
        $command->renewLicenses(2, 5, now()->addDays(30));
    })->not->toThrow();
});

it('can add licenses', function () {
    /** @var DatabaseManager&\Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class)->makePartial();
    $command = new HandleLicensesCommand($db);

    expect(function () use ($command) {
        $command->addLicenses(3, now()->addDays(30));
    })->not->toThrow();
});

it('can close licenses', function () {
    /** @var DatabaseManager&\Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class)->makePartial();
    $command = new HandleLicensesCommand($db);

    expect(function () use ($command) {
        $command->closeLicenses(4, now()->addDays(30));
    })->not->toThrow();
});
