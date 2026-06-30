<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

it('keeps the default command list unchanged without internals filter', function (): void {
    $this->artisan('list')
        ->expectsOutputToContain('cache:clear')
        ->assertExitCode(Command::SUCCESS);
});

it('filters command list to non vendor commands', function (): void {
    $this->artisan('list', ['--internals' => true])
        ->expectsOutputToContain('scout:check-index')
        ->doesntExpectOutputToContain('cache:clear')
        ->assertExitCode(Command::SUCCESS);
});

it('filters json command list to non vendor commands', function (): void {
    Artisan::call('list', [
        '--internals' => true,
        '--format' => 'json',
    ]);

    $output = Artisan::output();
    $data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    $names = array_column($data['commands'], 'name');

    expect($names)
        ->toContain('scout:check-index')
        ->not->toContain('cache:clear');
});
