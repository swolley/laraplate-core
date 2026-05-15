<?php

declare(strict_types=1);

uses(Modules\Core\Tests\ApplicationTestCase::class);

use Illuminate\Database\Console\Migrations\StatusCommand as LaravelStatusCommand;
use Illuminate\Database\Migrations\Migrator as LaravelMigrator;
use Modules\Core\Overrides\Migrator;
use Modules\Core\Overrides\StatusCommand;

it('binds custom migrator and status command after application boot', function (): void {
    expect(app('migrator'))->toBeInstanceOf(Migrator::class)
        ->and(app(LaravelMigrator::class))->toBeInstanceOf(Migrator::class)
        ->and(app(LaravelStatusCommand::class))->toBeInstanceOf(StatusCommand::class);
});
