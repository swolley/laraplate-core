<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Modules\Core\Http\Requests\ModifyRequest;


it('normalizeRules splits piped strings and drops empty segments', function (): void {
    $request = new ModifyRequest();
    $method = new ReflectionMethod(ModifyRequest::class, 'normalizeRules');
    $method->setAccessible(true);

    $result = $method->invoke($request, ['required|integer||numeric', 'string']);

    expect($result)->toBe(['required', 'integer', 'numeric', 'string']);
});

it('normalizeRules keeps non-piped rules as-is', function (): void {
    $request = new ModifyRequest();
    $method = new ReflectionMethod(ModifyRequest::class, 'normalizeRules');
    $method->setAccessible(true);

    $result = $method->invoke($request, 'required');

    expect($result)->toBe(['required']);
});

it('coerces boolean attribute strings before validation', function (): void {
    $request = ModifyRequest::create('/app/crud/insert/core/settings', 'POST', [
        'encrypted' => 'yes',
        'name' => 'not-a-boolean',
    ]);

    $route = new Route(['POST'], '/app/crud/insert/{module}/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('module', 'core');
    $route->setParameter('entity', 'settings');
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setRouteResolver(fn (): Route => $route);

    $method = new ReflectionMethod(ModifyRequest::class, 'prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    // Setting declares encrypted as boolean; name must stay a string.
    expect($request->input('encrypted'))->toBeTrue()
        ->and($request->input('name'))->toBe('not-a-boolean');
});
