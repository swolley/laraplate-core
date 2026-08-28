<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Modules\Core\Http\Middleware\AddContext;
use Modules\Core\Http\Middleware\ApplyDatabaseSettingsOverlay;
use Modules\Core\Http\Middleware\LocalizationMiddleware;

/**
 * The middleware of a route group, with the closures Laravel Boost appends dropped.
 *
 * @return array<int, string>
 */
$group = static fn (string $name): array => array_values(array_filter(
    app(Router::class)->getMiddlewareGroups()[$name] ?? [],
    'is_string',
));

it('resolves the request locale on the app surface', function () use ($group): void {
    // Until this was registered, nothing outside the Filament panel called
    // App::setLocale(): LocaleScope and HasTranslations read App::getLocale(), so /app
    // answered every request in the default language whatever the user asked for.
    expect($group('web'))->toContain(LocalizationMiddleware::class);
});

it('resolves the request locale on the public api surface', function () use ($group): void {
    expect($group('api'))->toContain(LocalizationMiddleware::class);
});

it('resolves the locale after the database settings overlay', function () use ($group): void {
    $web = $group('web');

    // The overlay copies every dotted setting onto config, app.locale included. Running
    // it after the locale resolver would throw away the language chosen for this user.
    expect(array_search(LocalizationMiddleware::class, $web, true))
        ->toBeGreaterThan(array_search(ApplyDatabaseSettingsOverlay::class, $web, true));
});

it('tags log context with the surface that handled the request', function () use ($group): void {
    // The scope used to be the literal 'web' for every surface, which made the field
    // useless for the one thing it is there for: telling /app apart from /api/v1.
    expect($group('web'))->toContain(AddContext::class . ':app')
        ->and($group('api'))->toContain(AddContext::class . ':api');
});

it('adds log context after the locale is resolved', function () use ($group): void {
    $web = $group('web');

    // AddContext records the locale, so it has to run downstream of the resolver.
    expect(array_search(AddContext::class . ':app', $web, true))
        ->toBeGreaterThan(array_search(LocalizationMiddleware::class, $web, true));
});
