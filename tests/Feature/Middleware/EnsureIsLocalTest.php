<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Modules\Core\Http\Middleware\EnsureIsLocal;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

test('middleware passes when app is local', function (): void {
    App::partialMock()->shouldReceive('isLocal')->andReturn(true);

    $request = Request::create('/test');
    $next = fn ($req) => response('ok');
    $middleware = new EnsureIsLocal;

    $response = $middleware->handle($request, $next);

    expect($response->getContent())->toBe('ok');
});

test('middleware aborts with 401 when app is not local', function (): void {
    App::partialMock()->shouldReceive('isLocal')->andReturn(false);

    $request = Request::create('/test');
    $next = fn ($req) => response('ok');
    $middleware = new EnsureIsLocal;

    $middleware->handle($request, $next);
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class, 'Unauthorized');

test('middleware has correct class structure', function (): void {
    $reflection = new ReflectionClass(EnsureIsLocal::class);

    expect($reflection->getName())->toBe('Modules\Core\Http\Middleware\EnsureIsLocal');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('middleware handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(EnsureIsLocal::class, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('mixed');
});

test('middleware uses correct imports', function (): void {
    $reflection = new ReflectionClass(EnsureIsLocal::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use Closure;');
    expect($source)->toContain('use Illuminate\Http\Request;');
    expect($source)->toContain('use Illuminate\Support\Facades\App;');
});

test('middleware uses App::isLocal method', function (): void {
    $reflection = new ReflectionClass(EnsureIsLocal::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('App::isLocal()');
});

test('middleware uses abort_unless helper', function (): void {
    $reflection = new ReflectionClass(EnsureIsLocal::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('abort_unless');
    expect($source)->toContain('401');
    expect($source)->toContain('Unauthorized');
});

test('middleware calls next handler', function (): void {
    $reflection = new ReflectionClass(EnsureIsLocal::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('return $next($request);');
});

test('middleware has proper conditional logic', function (): void {
    $reflection = new ReflectionClass(EnsureIsLocal::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('abort_unless(App::isLocal(), 401, \'Unauthorized\');');
});
