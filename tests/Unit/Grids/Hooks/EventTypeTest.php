<?php

declare(strict_types=1);

use Modules\Core\Grids\Hooks\EventType;

test('enum has correct class structure', function (): void {
    $reflection = new ReflectionClass(EventType::class);

    expect($reflection->getName())->toBe('Modules\Core\Grids\Hooks\EventType');
    expect($reflection->isEnum())->toBeTrue();
});

test('enum has all required cases', function (): void {
    $reflection = new ReflectionClass(EventType::class);
    $cases = $reflection->getConstants();

    expect($cases)->toHaveKey('PRE_SELECT');
    expect($cases)->toHaveKey('POST_SELECT');
    expect($cases)->toHaveKey('PRE_INSERT');
    expect($cases)->toHaveKey('POST_INSERT');
    expect($cases)->toHaveKey('PRE_UPDATE');
    expect($cases)->toHaveKey('POST_UPDATE');
    expect($cases)->toHaveKey('PRE_DELETE');
    expect($cases)->toHaveKey('POST_DELETE');
});

test('enum cases have correct string values', function (): void {
    expect(EventType::PRE_SELECT->value)->toBe('retrieving');
    expect(EventType::POST_SELECT->value)->toBe('retrieved');
    expect(EventType::PRE_INSERT->value)->toBe('creating');
    expect(EventType::POST_INSERT->value)->toBe('created');
    expect(EventType::PRE_UPDATE->value)->toBe('updating');
    expect(EventType::POST_UPDATE->value)->toBe('updated');
    expect(EventType::PRE_DELETE->value)->toBe('deleting');
    expect(EventType::POST_DELETE->value)->toBe('deleted');
});

test('enum is backed by string', function (): void {
    $reflection = new ReflectionClass(EventType::class);

    expect($reflection->isEnum())->toBeTrue();
    expect(EventType::PRE_SELECT->value)->toBeString();
});

test('enum has correct namespace', function (): void {
    $reflection = new ReflectionClass(EventType::class);

    expect($reflection->getNamespaceName())->toBe('Modules\Core\Grids\Hooks');
    expect($reflection->getShortName())->toBe('EventType');
});

test('enum cases can be accessed by value', function (): void {
    expect(EventType::from('retrieving'))->toBe(EventType::PRE_SELECT);
    expect(EventType::from('retrieved'))->toBe(EventType::POST_SELECT);
    expect(EventType::from('creating'))->toBe(EventType::PRE_INSERT);
    expect(EventType::from('created'))->toBe(EventType::POST_INSERT);
    expect(EventType::from('updating'))->toBe(EventType::PRE_UPDATE);
    expect(EventType::from('updated'))->toBe(EventType::POST_UPDATE);
    expect(EventType::from('deleting'))->toBe(EventType::PRE_DELETE);
    expect(EventType::from('deleted'))->toBe(EventType::POST_DELETE);
});

test('enum has tryFrom method', function (): void {
    expect(EventType::tryFrom('retrieving'))->toBe(EventType::PRE_SELECT);
    expect(EventType::tryFrom('invalid'))->toBeNull();
});

test('enum has cases method', function (): void {
    $cases = EventType::cases();

    expect($cases)->toHaveCount(8);
    expect($cases)->toContain(EventType::PRE_SELECT);
    expect($cases)->toContain(EventType::POST_SELECT);
    expect($cases)->toContain(EventType::PRE_INSERT);
    expect($cases)->toContain(EventType::POST_INSERT);
    expect($cases)->toContain(EventType::PRE_UPDATE);
    expect($cases)->toContain(EventType::POST_UPDATE);
    expect($cases)->toContain(EventType::PRE_DELETE);
    expect($cases)->toContain(EventType::POST_DELETE);
});
