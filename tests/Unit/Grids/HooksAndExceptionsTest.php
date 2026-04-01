<?php

declare(strict_types=1);

use Modules\Core\Grids\Exceptions\ConcurrencyException;
use Modules\Core\Tests\Stubs\HooksHarness;
use Symfony\Component\HttpFoundation\Response;

it('stores and returns read hooks', function (): void {
    $harness = new HooksHarness();

    $pre = static fn (): string => 'pre';
    $post = static fn (): string => 'post';

    expect($harness->onPreSelect($pre))->toBe($harness)
        ->and($harness->onPostSelect($post))->toBe($harness)
        ->and($harness->onPreSelect())->toBe($pre)
        ->and($harness->onPostSelect())->toBe($post);
});

it('stores and returns write hooks', function (): void {
    $harness = new HooksHarness();

    $pre_insert = static fn (): string => 'pre_insert';
    $post_insert = static fn (): string => 'post_insert';
    $pre_update = static fn (): string => 'pre_update';
    $post_update = static fn (): string => 'post_update';
    $pre_delete = static fn (): string => 'pre_delete';
    $post_delete = static fn (): string => 'post_delete';

    $harness
        ->onPreInsert($pre_insert)
        ->onPostInsert($post_insert)
        ->onPreUpdate($pre_update)
        ->onPostUpdate($post_update)
        ->onPreDelete($pre_delete)
        ->onPostDelete($post_delete);

    expect($harness->onPreInsert())->toBe($pre_insert)
        ->and($harness->onPostInsert())->toBe($post_insert)
        ->and($harness->onPreUpdate())->toBe($pre_update)
        ->and($harness->onPostUpdate())->toBe($post_update)
        ->and($harness->onPreDelete())->toBe($pre_delete)
        ->and($harness->onPostDelete())->toBe($post_delete);
});

it('builds concurrency exception with conflict status code', function (): void {
    $default = new ConcurrencyException();
    $custom = new ConcurrencyException('custom-message');

    expect($default->getMessage())->toBe("You don't have the last version of the records")
        ->and($default->getCode())->toBe(Response::HTTP_CONFLICT)
        ->and($custom->getMessage())->toBe('custom-message')
        ->and($custom->getCode())->toBe(Response::HTTP_CONFLICT);
});
