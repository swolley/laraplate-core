<?php

declare(strict_types=1);

use Modules\Core\Http\Middleware\LocalizationMiddleware;

test('middleware has correct class structure', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);

    expect($reflection->getName())->toBe('Modules\Core\Http\Middleware\LocalizationMiddleware');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('middleware handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LocalizationMiddleware::class, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('Symfony\Component\HttpFoundation\Response');
});

test('middleware uses correct imports', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use Closure;');
    expect($source)->toContain('use Illuminate\Http\Request;');
    expect($source)->toContain('use Illuminate\Support\Facades\App;');
    expect($source)->toContain('use Illuminate\Support\Str;');
    expect($source)->toContain('use Symfony\Component\HttpFoundation\Response;');
});

test('middleware handles user locale setting', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$user = $request->user();');
    expect($source)->toContain('$user->lang');
    expect($source)->toContain('App::getLocale()');
    expect($source)->toContain('App::setLocale($user->lang)');
});

test('middleware handles preferred language', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('getPreferredLanguage()');
    expect($source)->toContain('Str::of($lang)->before(\'_\')->value()');
});

test('middleware handles language validation', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$lang !== null');
    expect($source)->toContain('$lang !== \'\'');
    expect($source)->toContain('$lang !== \'0\'');
});

test('middleware calls next handler', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('return $next($request);');
});

test('middleware has proper conditional logic', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('if ($user && $user->lang !== App::getLocale())');
    expect($source)->toContain('elseif (! $user)');
});

test('middleware handles string manipulation', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Str::of($lang)->before(\'_\')');
    expect($source)->toContain('->value()');
});
