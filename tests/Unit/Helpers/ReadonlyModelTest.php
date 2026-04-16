<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Modules\Core\Helpers\Exceptions\ReadOnlyModelException;
use Modules\Core\Tests\Unit\Helpers\ReadonlyModelStub;
use Modules\Core\Tests\Unit\Helpers\ReadonlySoftDeletingStub;

beforeEach(function (): void {
    if (Model::getEventDispatcher() === null) {
        Model::setEventDispatcher(new Dispatcher(new Container));
    }

    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $schema = $capsule->getConnection()->getSchemaBuilder();

    $schema->dropIfExists('readonly_model_stubs');
    $schema->dropIfExists('readonly_soft_deleting_stubs');

    $schema->create('readonly_model_stubs', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $schema->create('readonly_soft_deleting_stubs', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->softDeletes();
        $table->timestamps();
    });
});

it('prevents creating a read-only model', function (): void {
    expect(fn () => ReadonlyModelStub::query()->create(['name' => 'blocked']))
        ->toThrow(ReadOnlyModelException::class, 'Cannot create model');
});

it('allows create when bypass is active', function (): void {
    $model = ReadonlyModelStub::withoutReadOnlyGuards(
        fn () => ReadonlyModelStub::query()->create(['name' => 'allowed'])
    );

    expect($model->exists)->toBeTrue()
        ->and($model->name)->toBe('allowed');
});

it('prevents updating a read-only model', function (): void {
    $model = ReadonlyModelStub::withoutReadOnlyGuards(
        fn () => ReadonlyModelStub::query()->create(['name' => 'original'])
    );

    $model->name = 'changed';

    expect(fn () => $model->save())->toThrow(ReadOnlyModelException::class, 'Cannot update model');
});

it('prevents deleting a read-only model', function (): void {
    $model = ReadonlyModelStub::withoutReadOnlyGuards(
        fn () => ReadonlyModelStub::query()->create(['name' => 'to-delete'])
    );

    expect(fn () => $model->delete())->toThrow(ReadOnlyModelException::class, 'Cannot delete model');
});

it('prevents restoring a soft-deleted read-only model', function (): void {
    $model = ReadonlySoftDeletingStub::withoutReadOnlyGuards(
        fn () => ReadonlySoftDeletingStub::query()->create(['name' => 'trash-me'])
    );

    ReadonlySoftDeletingStub::withoutReadOnlyGuards(fn () => $model->delete());

    expect($model->trashed())->toBeTrue();

    expect(fn () => $model->restore())->toThrow(ReadOnlyModelException::class, 'Cannot restore model');
});

it('prevents force-deleting a read-only model', function (): void {
    $model = ReadonlySoftDeletingStub::withoutReadOnlyGuards(
        fn () => ReadonlySoftDeletingStub::query()->create(['name' => 'force-me'])
    );

    ReadonlySoftDeletingStub::withoutReadOnlyGuards(fn () => $model->delete());

    expect(fn () => $model->forceDelete())->toThrow(ReadOnlyModelException::class, 'Cannot force-delete model');
});
