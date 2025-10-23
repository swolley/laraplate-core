<?php

declare(strict_types=1);

use Modules\Core\Http\Middleware\EnsureIsSuperAdmin;

test('middleware has correct class structure', function (): void {
    $reflection = new ReflectionClass(EnsureIsSuperAdmin::class);

    expect($reflection->getName())->toBe('Modules\Core\Http\Middleware\EnsureIsSuperAdmin');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('middleware handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(EnsureIsSuperAdmin::class, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType())->toBeNull();
});

test('middleware uses correct imports', function (): void {
    $reflection = new ReflectionClass(EnsureIsSuperAdmin::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use Closure;');
    expect($source)->toContain('use Illuminate\Http\Request;');
    expect($source)->toContain('use Illuminate\Support\Facades\Auth;');
});

test('middleware uses Auth facade methods', function (): void {
    $reflection = new ReflectionClass(EnsureIsSuperAdmin::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Auth::check()');
    expect($source)->toContain('Auth::user()');
});

test('middleware checks isSuperAdmin method', function (): void {
    $reflection = new ReflectionClass(EnsureIsSuperAdmin::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('isSuperAdmin()');
});

test('middleware uses abort_if helper', function (): void {
    $reflection = new ReflectionClass(EnsureIsSuperAdmin::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('abort_if');
    expect($source)->toContain('401');
    expect($source)->toContain('Unauthorized');
});

test('middleware calls next handler', function (): void {
    $reflection = new ReflectionClass(EnsureIsSuperAdmin::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('return $next($request);');
});

test('middleware has proper conditional logic', function (): void {
    $reflection = new ReflectionClass(EnsureIsSuperAdmin::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('abort_if(! Auth::check() || Auth::user()->isSuperAdmin(), 401, \'Unauthorized\');');
});
