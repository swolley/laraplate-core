<?php

declare(strict_types=1);

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
