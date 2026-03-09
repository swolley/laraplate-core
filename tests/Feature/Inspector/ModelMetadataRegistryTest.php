<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Inspector\ModelMetadata;
use Modules\Core\Inspector\ModelMetadataRegistry;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    ModelMetadataRegistry::reset();
});

it('returns the same singleton instance', function (): void {
    $first = ModelMetadataRegistry::getInstance();
    $second = ModelMetadataRegistry::getInstance();

    expect($first)->toBe($second);
});

it('returns ModelMetadata for a valid model class', function (): void {
    $meta = ModelMetadataRegistry::getInstance()->get(User::class);

    expect($meta)
        ->toBeInstanceOf(ModelMetadata::class)
        ->and($meta->class)->toBe(User::class)
        ->and($meta->table)->toBe('users')
        ->and($meta->timestamps)->toBeTrue()
        ->and($meta->incrementing)->toBeTrue()
        ->and($meta->keyName)->toBe('id')
        ->and($meta->keyType)->toBe('int');
});

it('memoizes metadata for the same class', function (): void {
    $registry = ModelMetadataRegistry::getInstance();
    $first = $registry->get(User::class);
    $second = $registry->get(User::class);

    expect($first)->toBe($second);
});

it('has() returns true after get() and false before', function (): void {
    $registry = ModelMetadataRegistry::getInstance();

    expect($registry->has(User::class))->toBeFalse();

    $registry->get(User::class);

    expect($registry->has(User::class))->toBeTrue();
});

it('forget() removes a cached entry', function (): void {
    $registry = ModelMetadataRegistry::getInstance();
    $registry->get(User::class);

    $registry->forget(User::class);

    expect($registry->has(User::class))->toBeFalse();
});

it('clearAll() removes all cached entries', function (): void {
    $registry = ModelMetadataRegistry::getInstance();
    $registry->get(User::class);

    $registry->clearAll();

    expect($registry->has(User::class))->toBeFalse();
});

it('reset() creates a fresh instance', function (): void {
    $first = ModelMetadataRegistry::getInstance();
    $first->get(User::class);

    ModelMetadataRegistry::reset();

    $second = ModelMetadataRegistry::getInstance();

    expect($first)->not->toBe($second)
        ->and($second->has(User::class))->toBeFalse();
});

it('resolves trait flags correctly for User model', function (): void {
    $meta = ModelMetadataRegistry::getInstance()->get(User::class);

    expect($meta->traits)->toBeArray()->not->toBeEmpty()
        ->and($meta->hasSoftDeletes)->toBeBool()
        ->and($meta->hasValidity)->toBeBool()
        ->and($meta->hasActivation)->toBeBool()
        ->and($meta->hasLocks)->toBeBool()
        ->and($meta->hasSorts)->toBeBool()
        ->and($meta->hasSearchable)->toBeBool()
        ->and($meta->hasTranslations)->toBeBool()
        ->and($meta->hasGridUtils)->toBeBool()
        ->and($meta->hasCache)->toBeBool();
});

it('hasTrait() checks trait presence correctly', function (): void {
    $meta = ModelMetadataRegistry::getInstance()->get(User::class);
    $traits = class_uses_recursive(User::class);

    if ($traits !== []) {
        $first_trait = array_values($traits)[0];
        expect($meta->hasTrait($first_trait))->toBeTrue();
    }

    expect($meta->hasTrait('NonExistent\\Trait\\Name'))->toBeFalse();
});
