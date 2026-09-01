<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| Each module registers its own resources/views root as an anonymous component
| path, so <x-{module}::layouts.master> resolves to the module's own blade file.
| Without it the Blade compiler only finds class components or a components/
| subfolder, and `view:cache` aborts on the module scaffold views, which means
| views cannot be precompiled on deploy.
|--------------------------------------------------------------------------
*/

afterEach(function (): void {
    Artisan::call('view:clear');
});

it('registers an anonymous component path for the module', function (): void {
    $prefixes = array_column(Blade::getAnonymousComponentPaths(), 'prefix');

    expect($prefixes)->toContain('core');
});

it('compiles every blade template in the application', function (): void {
    $this->artisan('view:cache')->assertSuccessful();
});
