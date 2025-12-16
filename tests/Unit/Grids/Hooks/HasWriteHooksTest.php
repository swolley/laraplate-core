<?php

declare(strict_types=1);

use Modules\Core\Grids\Hooks\HasWriteHooks;

it('trait has correct class structure', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);

    expect($reflection->getName())->toBe('Modules\Core\Grids\Hooks\HasWriteHooks');
    expect($reflection->isTrait())->toBeTrue();
});

it('trait has writeEvents property', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);

    expect($reflection->hasProperty('writeEvents'))->toBeTrue();

    $property = $reflection->getProperty('writeEvents');
    expect($property->isPrivate())->toBeTrue();
    expect($property->getType()->getName())->toBe('array');
});

it('trait has all required methods', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);

    expect($reflection->hasMethod('onPreInsert'))->toBeTrue();
    expect($reflection->hasMethod('onPostInsert'))->toBeTrue();
    expect($reflection->hasMethod('onPreUpdate'))->toBeTrue();
    expect($reflection->hasMethod('onPostUpdate'))->toBeTrue();
    expect($reflection->hasMethod('onPreDelete'))->toBeTrue();
    expect($reflection->hasMethod('onPostDelete'))->toBeTrue();
});

it('trait onPreInsert method has correct signature', function (): void {
    $reflection = new ReflectionMethod(HasWriteHooks::class, 'onPreInsert');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType())->toBeNull();
    expect($reflection->isPublic())->toBeTrue();
});

it('trait onPostInsert method has correct signature', function (): void {
    $reflection = new ReflectionMethod(HasWriteHooks::class, 'onPostInsert');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType())->toBeNull();
    expect($reflection->isPublic())->toBeTrue();
});

it('trait onPreUpdate method has correct signature', function (): void {
    $reflection = new ReflectionMethod(HasWriteHooks::class, 'onPreUpdate');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType())->toBeNull();
    expect($reflection->isPublic())->toBeTrue();
});

it('trait onPostUpdate method has correct signature', function (): void {
    $reflection = new ReflectionMethod(HasWriteHooks::class, 'onPostUpdate');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType())->toBeNull();
    expect($reflection->isPublic())->toBeTrue();
});

it('trait onPreDelete method has correct signature', function (): void {
    $reflection = new ReflectionMethod(HasWriteHooks::class, 'onPreDelete');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType())->toBeNull();
    expect($reflection->isPublic())->toBeTrue();
});

it('trait onPostDelete method has correct signature', function (): void {
    $reflection = new ReflectionMethod(HasWriteHooks::class, 'onPostDelete');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType())->toBeNull();
    expect($reflection->isPublic())->toBeTrue();
});

it('trait methods use EventType enum values', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('EventType::PRE_INSERT->value');
    expect($source)->toContain('EventType::POST_INSERT->value');
    expect($source)->toContain('EventType::PRE_UPDATE->value');
    expect($source)->toContain('EventType::POST_UPDATE->value');
    expect($source)->toContain('EventType::PRE_DELETE->value');
    expect($source)->toContain('EventType::POST_DELETE->value');
});

it('trait methods handle callback parameters', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('?callable $callback = null');
    // Verifica che ci sia un controllo sul callback (può essere scritto in modi diversi)
    expect($source)->toMatch('/(if\s*\(\s*!?\s*\$callback|if\s*\(\s*\$callback\s*===?\s*null)/');
    expect($source)->toContain('return $this->writeEvents[EventType::');
});

it('trait methods set callbacks in writeEvents array', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$this->writeEvents[EventType::');
    expect($source)->toContain('] = $callback;');
    expect($source)->toContain('return $this;');
});

it('trait methods return callbacks when no parameter provided', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('return $this->writeEvents[EventType::');
    expect($source)->toContain('] ?? null;');
});

it('trait methods support method chaining', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('return $this;');
});

it('trait has correct namespace', function (): void {
    $reflection = new ReflectionClass(HasWriteHooks::class);

    expect($reflection->getNamespaceName())->toBe('Modules\Core\Grids\Hooks');
    expect($reflection->getShortName())->toBe('HasWriteHooks');
});
