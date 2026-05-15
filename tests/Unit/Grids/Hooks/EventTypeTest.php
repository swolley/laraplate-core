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
    expect(EventType::PreSelect->value)->toBe('retrieving');
    expect(EventType::PostSelect->value)->toBe('retrieved');
    expect(EventType::PreInsert->value)->toBe('creating');
    expect(EventType::PostInsert->value)->toBe('created');
    expect(EventType::PreUpdate->value)->toBe('updating');
    expect(EventType::PostUpdate->value)->toBe('updated');
    expect(EventType::PreDelete->value)->toBe('deleting');
    expect(EventType::PostDelete->value)->toBe('deleted');
});

test('enum is backed by string', function (): void {
    $reflection = new ReflectionClass(EventType::class);

    expect($reflection->isEnum())->toBeTrue();
    expect(EventType::PreSelect->value)->toBeString();
});

test('enum has correct namespace', function (): void {
    $reflection = new ReflectionClass(EventType::class);

    expect($reflection->getNamespaceName())->toBe('Modules\Core\Grids\Hooks');
    expect($reflection->getShortName())->toBe('EventType');
});

test('enum cases can be accessed by value', function (): void {
    expect(EventType::from('retrieving'))->toBe(EventType::PreSelect);
    expect(EventType::from('retrieved'))->toBe(EventType::PostSelect);
    expect(EventType::from('creating'))->toBe(EventType::PreInsert);
    expect(EventType::from('created'))->toBe(EventType::PostInsert);
    expect(EventType::from('updating'))->toBe(EventType::PreUpdate);
    expect(EventType::from('updated'))->toBe(EventType::PostUpdate);
    expect(EventType::from('deleting'))->toBe(EventType::PreDelete);
    expect(EventType::from('deleted'))->toBe(EventType::PostDelete);
});

test('enum has tryFrom method', function (): void {
    expect(EventType::tryFrom('retrieving'))->toBe(EventType::PreSelect);
    expect(EventType::tryFrom('invalid'))->toBeNull();
});

test('enum has cases method', function (): void {
    $cases = EventType::cases();

    expect($cases)->toHaveCount(8);
    expect($cases)->toContain(EventType::PreSelect);
    expect($cases)->toContain(EventType::PostSelect);
    expect($cases)->toContain(EventType::PreInsert);
    expect($cases)->toContain(EventType::PostInsert);
    expect($cases)->toContain(EventType::PreUpdate);
    expect($cases)->toContain(EventType::PostUpdate);
    expect($cases)->toContain(EventType::PreDelete);
    expect($cases)->toContain(EventType::PostDelete);
});
