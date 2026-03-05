<?php

declare(strict_types=1);

use Modules\Core\Listeners\AfterLoginListener;

it('listener has correct class structure', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);

    expect($reflection->getName())->toBe('Modules\Core\Listeners\AfterLoginListener');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
    expect($reflection->hasMethod('checkUserLicense'))->toBeTrue();
});

it('listener handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(AfterLoginListener::class, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('void');
    expect($reflection->isPublic())->toBeTrue();
});

it('listener checkUserLicense method has correct signature', function (): void {
    $reflection = new ReflectionMethod(AfterLoginListener::class, 'checkUserLicense');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('void');
    expect($reflection->isStatic())->toBeTrue();
    expect($reflection->isPublic())->toBeTrue();
});
