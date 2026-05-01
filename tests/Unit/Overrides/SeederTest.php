<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Modules\Core\Overrides\Seeder;


it('constructs with DatabaseManager', function (): void {
    $db = app(DatabaseManager::class);

    $seeder = new class($db) extends Seeder
    {
        public function run(): void {}
    };

    expect($seeder)->toBeInstanceOf(Seeder::class);
});

it('run can be overridden', function (): void {
    $ran = false;
    $seeder = new class(app(DatabaseManager::class)) extends Seeder
    {
        public function run(): void
        {
            $GLOBALS['__seeder_ran'] = true;
        }
    };
    $seeder->run();
    expect($GLOBALS['__seeder_ran'] ?? false)->toBeTrue();
    unset($GLOBALS['__seeder_ran']);
});

it('constructor starts benchmark when debug is true', function (): void {
    config()->set('app.debug', true);

    $seeder = new class(app(DatabaseManager::class)) extends Seeder
    {
        public function run(): void {}

        public function hasBenchmarkStarted(): bool
        {
            return $this->benchmarkStartTime !== null;
        }

        protected function endBenchmark(): void {}
    };

    expect($seeder->hasBenchmarkStarted())->toBeTrue();
});

it('destructor calls endBenchmark when debug is true', function (): void {
    config()->set('app.debug', true);

    $seeder = new class(app(DatabaseManager::class)) extends Seeder
    {
        public bool $benchmarkEnded = false;

        public function run(): void {}

        protected function endBenchmark(): void
        {
            $this->benchmarkEnded = true;
        }
    };

    $ended_ref = &$seeder->benchmarkEnded;
    $seeder->__destruct();

    expect($ended_ref)->toBeTrue();
});
