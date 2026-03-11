<?php

declare(strict_types=1);

use Modules\Core\Services\Crud\DTOs\CrudMeta;
use Modules\Core\Services\Crud\DTOs\CrudResult;
use Modules\Core\Tests\TestCase;

uses(TestCase::class);

it('wraps data and meta into a CrudResult dto', function (): void {
    $meta = new CrudMeta(totalRecords: 10, currentRecords: 5);
    $data = ['id' => 1, 'name' => 'Foo'];

    $result = new CrudResult(
        data: $data,
        meta: $meta,
        error: null,
        statusCode: 200,
    );

    expect($result->data)->toBe($data)
        ->and($result->meta)->toBe($meta)
        ->and($result->error)->toBeNull()
        ->and($result->statusCode)->toBe(200);
});

it('can represent an error result without data', function (): void {
    $result = new CrudResult(
        data: null,
        meta: null,
        error: 'Something went wrong',
        statusCode: 500,
    );

    expect($result->data)->toBeNull()
        ->and($result->meta)->toBeNull()
        ->and($result->error)->toBe('Something went wrong')
        ->and($result->statusCode)->toBe(500);
});

