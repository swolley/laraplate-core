<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Overrides\RouteListCommand as CoreRouteListCommand;
use Symfony\Component\Console\Command\Command;

it('binds the framework route list command to the Core override', function (): void {
    $command = app(Illuminate\Foundation\Console\RouteListCommand::class);

    expect($command)->toBeInstanceOf(CoreRouteListCommand::class)
        ->and($command->getDescription())->toContain('<fg=green>(⚡ Modules\Core)</fg=green>');
});

it('filters route list to internal app and api routes', function (): void {
    Route::get('app/core-route-list-test', static fn (): string => 'app')->name('core.route-list-test.app');
    Route::get('api/core-route-list-test', static fn (): string => 'api')->name('core.route-list-test.api');
    Route::get('public/core-route-list-test', static fn (): string => 'public')->name('core.route-list-test.public');

    $this->artisan('route:list', ['--internals' => true])
        ->expectsOutputToContain('app/core-route-list-test')
        ->expectsOutputToContain('api/core-route-list-test')
        ->doesntExpectOutputToContain('public/core-route-list-test')
        ->assertExitCode(Command::SUCCESS);
});
