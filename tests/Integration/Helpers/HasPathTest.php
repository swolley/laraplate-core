<?php

declare(strict_types=1);

use Modules\Core\Tests\Fixtures\HasPathStubModel;

it('keeps the path accessor available without appending it to every serialization', function (): void {
    $model = new HasPathStubModel(['id' => 7, 'slug' => 'leaf']);
    $model->exists = true;

    // On-demand: callers that already loaded a tree (or otherwise need the path) can
    // still read it. Serialization must not pay getPath() for every row in a list.
    expect($model->path)->toBe('has_path_stubs/root/child/leaf/7')
        ->and($model->toArray())->not->toHaveKey('path');
});
