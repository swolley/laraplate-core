<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Modules\Core\Jobs\CreateVersionJob;
use Modules\Core\Tests\Unit\Helpers\VersionableStub;
use Tests\TestCase;

uses(TestCase::class);

it('exposes the versioning entrypoint', function (): void {
    $model = new VersionableStub();

    expect(method_exists($model, 'createVersion'))->toBeTrue();
});

it('dispatches async job when enabled', function (): void {
    Bus::fake();

    $model = new VersionableStub();
    $model->setRawAttributes(['id' => 1]);
    $model->exists = true;

    $model->createVersion();

    Bus::assertDispatched(CreateVersionJob::class);
});
