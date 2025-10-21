<?php

declare(strict_types=1);

use Modules\Core\Http\Middleware\PreviewMiddleware;

test('middleware has correct class structure', function (): void {
    $reflection = new ReflectionClass(PreviewMiddleware::class);
    
    expect($reflection->getName())->toBe('Modules\Core\Http\Middleware\PreviewMiddleware');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('middleware handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(PreviewMiddleware::class, 'handle');
    
    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('Symfony\Component\HttpFoundation\Response');
});

test('middleware uses correct imports', function (): void {
    $reflection = new ReflectionClass(PreviewMiddleware::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('use Closure;');
    expect($source)->toContain('use Illuminate\Http\Request;');
    expect($source)->toContain('use Symfony\Component\HttpFoundation\Response;');
});

test('middleware checks for preview parameter', function (): void {
    $reflection = new ReflectionClass(PreviewMiddleware::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('$request->has(\'preview\')');
    expect($source)->toContain('$request->get(\'preview\'');
});

test('middleware handles preview parameter values', function (): void {
    $reflection = new ReflectionClass(PreviewMiddleware::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('preview($request->get(\'preview\', \'false\')');
    expect($source)->toContain('=== \'true\'');
    expect($source)->toContain('=== true');
});

test('middleware calls preview helper function', function (): void {
    $reflection = new ReflectionClass(PreviewMiddleware::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('preview(');
    expect($source)->toContain('$request->get(\'preview\', \'false\')');
});

test('middleware calls next handler', function (): void {
    $reflection = new ReflectionClass(PreviewMiddleware::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('return $next($request);');
});

test('middleware has proper conditional logic', function (): void {
    $reflection = new ReflectionClass(PreviewMiddleware::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('if ($request->has(\'preview\'))');
});

test('middleware handles boolean conversion', function (): void {
    $reflection = new ReflectionClass(PreviewMiddleware::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('=== \'true\' || $request->get(\'preview\', \'false\') === true');
});
