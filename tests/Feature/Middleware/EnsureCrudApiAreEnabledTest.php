<?php

declare(strict_types=1);

use Modules\Core\Http\Middleware\EnsureCrudApiAreEnabled;

test('middleware has correct class structure', function (): void {
    $reflection = new ReflectionClass(EnsureCrudApiAreEnabled::class);
    
    expect($reflection->getName())->toBe('Modules\Core\Http\Middleware\EnsureCrudApiAreEnabled');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('middleware handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(EnsureCrudApiAreEnabled::class, 'handle');
    
    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType())->toBeNull();
});

test('middleware uses correct imports', function (): void {
    $reflection = new ReflectionClass(EnsureCrudApiAreEnabled::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('use Closure;');
    expect($source)->toContain('use Illuminate\Http\Request;');
});

test('middleware uses correct config key', function (): void {
    $reflection = new ReflectionClass(EnsureCrudApiAreEnabled::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('core.expose_crud_api');
    expect($source)->toContain('config(');
});

test('middleware uses abort_unless helper', function (): void {
    $reflection = new ReflectionClass(EnsureCrudApiAreEnabled::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('abort_unless');
    expect($source)->toContain('403');
    expect($source)->toContain('Forbidden');
});

test('middleware calls next handler', function (): void {
    $reflection = new ReflectionClass(EnsureCrudApiAreEnabled::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('return $next($request);');
});

test('middleware has proper conditional logic', function (): void {
    $reflection = new ReflectionClass(EnsureCrudApiAreEnabled::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('abort_unless(config(\'core.expose_crud_api\'), 403, \'Forbidden\');');
});
