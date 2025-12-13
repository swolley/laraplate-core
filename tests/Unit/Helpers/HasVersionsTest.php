<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Jobs\CreateVersionJob;
use Tests\TestCase;

final class VersionableStub extends Model
{
    use HasVersions;

    protected $table = 'versionables';

    public function shouldBeVersioning(): bool
    {
        return true;
    }
}

final class HasVersionsTest extends TestCase
{
    public function test_exposes_the_versioning_entrypoint(): void
    {
        $model = new VersionableStub();

        $this->assertTrue(method_exists($model, 'createVersion'));
    }

    public function test_dispatches_async_job_when_enabled(): void
    {
        Bus::fake();

        $model = new VersionableStub();
        $model->setRawAttributes(['id' => 1]);
        $model->exists = true;

        $model->createVersion();

        Bus::assertDispatched(CreateVersionJob::class);
    }
}
