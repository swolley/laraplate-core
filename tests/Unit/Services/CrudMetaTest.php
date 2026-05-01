<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Core\Services\Crud\DTOs\CrudMeta;

it('exposes all metadata properties via constructor', function (): void {
    $cachedAt = new CarbonImmutable('2025-01-02 03:04:05');

    $meta = new CrudMeta(
        totalRecords: 100,
        currentRecords: 25,
        currentPage: 2,
        totalPages: 4,
        pagination: 25,
        from: 26,
        to: 50,
        class: 'App\\Models\\Post',
        table: 'posts',
        cachedAt: $cachedAt,
    );

    expect($meta->totalRecords)->toBe(100)
        ->and($meta->currentRecords)->toBe(25)
        ->and($meta->currentPage)->toBe(2)
        ->and($meta->totalPages)->toBe(4)
        ->and($meta->pagination)->toBe(25)
        ->and($meta->from)->toBe(26)
        ->and($meta->to)->toBe(50)
        ->and($meta->class)->toBe('App\\Models\\Post')
        ->and($meta->table)->toBe('posts')
        ->and($meta->cachedAt)->toBe($cachedAt);
});

it('defaults all metadata properties to null when not provided', function (): void {
    $meta = new CrudMeta();

    expect($meta->totalRecords)->toBeNull()
        ->and($meta->currentRecords)->toBeNull()
        ->and($meta->currentPage)->toBeNull()
        ->and($meta->totalPages)->toBeNull()
        ->and($meta->pagination)->toBeNull()
        ->and($meta->from)->toBeNull()
        ->and($meta->to)->toBeNull()
        ->and($meta->class)->toBeNull()
        ->and($meta->table)->toBeNull()
        ->and($meta->cachedAt)->toBeNull();
},
);
