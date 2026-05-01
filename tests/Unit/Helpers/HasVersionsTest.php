<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Jobs\CreateVersionJob;
use Modules\Core\Tests\Unit\Helpers\VersionableStub;


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

it('treats empty versionStrategy string as unset and resolves from settings', function (): void {
    $model = new class extends Model
    {
        use HasVersions;

        protected $table = 'users';

        public string $versionStrategy = '';

        public function shouldBeVersioning(): bool
        {
            return false;
        }
    };

    $model->setConnection(config('database.default'));

    expect(fn () => $model->getVersionStrategy())->not->toThrow(\ValueError::class);
});
