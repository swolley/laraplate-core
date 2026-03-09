<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('constructs with DatabaseManager', function (): void {
    $db = app(DatabaseManager::class);

    $seeder = new class($db) extends Seeder {
        public function run(): void
        {
        }
    };

    expect($seeder)->toBeInstanceOf(Seeder::class);
});

it('run can be overridden', function (): void {
    $ran = false;
    $seeder = new class(app(DatabaseManager::class)) extends Seeder {
        public function run(): void
        {
            $GLOBALS['__seeder_ran'] = true;
        }
    };
    $seeder->run();
    expect($GLOBALS['__seeder_ran'] ?? false)->toBeTrue();
    unset($GLOBALS['__seeder_ran']);
});
