<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\CMS\Casts\EntityType;
use Modules\CMS\Models\Entity;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Services\DynamicContentsService;

beforeEach(function (): void {
    Cache::flush();
    DynamicContentsService::reset();
});

it('clears all dynamic contents in-memory caches', function (): void {
    $service = DynamicContentsService::getInstance();

    $service->clearAllCaches();
    DynamicContentsService::reset();

    expect(DynamicContentsService::getInstance())->toBeInstanceOf(DynamicContentsService::class);
});

it('registers namespaced presettable memo keys for later invalidation', function (): void {
    $service = DynamicContentsService::getInstance();
    $reflection = new ReflectionMethod(DynamicContentsService::class, 'presettableMemoKey');
    $reflection->setAccessible(true);

    $key = $reflection->invoke($service, Presettable::class);

    expect($key)->toBe('core.dynamic_contents.presettables:' . hash('sha256', Presettable::class));
});

it('keeps entity in-memory cache buckets isolated by dynamic content type', function (): void {
    Entity::query()->create([
        'name' => 'Article',
        'slug' => 'article',
        'type' => EntityType::Contents,
    ]);

    $service = DynamicContentsService::getInstance();
    $contents = $service->fetchAvailableEntities(EntityType::Contents);

    $category = Entity::query()->create([
        'name' => 'Topic',
        'slug' => 'topic',
        'type' => EntityType::Categories,
    ]);

    $categories = $service->fetchAvailableEntities(EntityType::Categories);

    expect($contents)->toHaveCount(1)
        ->and($categories->pluck('id')->all())->toBe([$category->id]);
});
