<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\Seeders\SeedersRelationChildStubModel;
use Modules\Core\Tests\Stubs\Seeders\SeedersRelationParentStubModel;
use Modules\Core\Tests\Stubs\Seeders\SeedersRelationTagStubModel;
use Modules\Core\Tests\Stubs\Seeders\SeedersUtilsTestSeeder;
use Modules\Core\Tests\Stubs\SeedersBulkStubModel;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    Schema::create('seeders_bulk_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('seeders_relation_parents', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('seeders_relation_children', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('note')->nullable();
        $table->timestamps();
    });

    Schema::create('seeders_relation_tags', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('seeders_relation_child_tag', function (Blueprint $table): void {
        $table->unsignedBigInteger('seeders_relation_child_id');
        $table->unsignedBigInteger('seeders_relation_tag_id');
        $table->string('meta')->nullable();
    });
});

it('create persists a user from attributes', function (): void {
    $seeder = new SeedersUtilsTestSeeder;

    $user = $seeder->runCreate();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->exists)->toBeTrue()
        ->and(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
});

it('createMany bulk inserts and assigns incrementing ids', function (): void {
    $seeder = new SeedersUtilsTestSeeder;

    $models = $seeder->runCreateMany(3);

    expect($models)->toHaveCount(3)
        ->and(SeedersBulkStubModel::query()->count())->toBe(3);

    foreach ($models as $model) {
        expect($model->getKey())->not->toBeNull();
    }
});

it('createMany returns empty collection for empty items', function (): void {
    $seeder = new SeedersUtilsTestSeeder;

    expect($seeder->runCreateManyEmpty())->toBeEmpty();
});

it('create executes callable method relation fallback branch', function (): void {
    $seeder = new SeedersUtilsTestSeeder;

    $child = $seeder->runCreateWithCallableMethod('custom-note');

    expect($child->note)->toBe('custom-note');
});

it('create associates belongs-to relations', function (): void {
    $seeder = new SeedersUtilsTestSeeder;
    $parent = SeedersRelationParentStubModel::query()->create(['name' => 'parent']);

    $child = $seeder->runCreateWithBelongsTo($parent);

    expect($child->parent_id)->toBe($parent->id);
});

it('create syncs belongs-to-many relations with and without pivot values', function (): void {
    $seeder = new SeedersUtilsTestSeeder;
    $tags = collect([
        SeedersRelationTagStubModel::query()->create(['name' => 't1']),
        SeedersRelationTagStubModel::query()->create(['name' => 't2']),
    ]);

    $child_a = $seeder->runCreateWithBelongsToMany($tags);
    $child_b = $seeder->runCreateWithBelongsToManyPivot($tags);

    expect($child_a->tags()->count())->toBe(2)
        ->and($child_b->tags()->count())->toBe(2)
        ->and(
            Illuminate\Support\Facades\DB::table('seeders_relation_child_tag')
                ->where('seeders_relation_child_id', $child_b->id)
                ->where('meta', 'seeded')
                ->exists(),
        )->toBeTrue();
});

it('logOperation writes creating/updating output', function (): void {
    $seeder = new SeedersUtilsTestSeeder;
    $command = Mockery::mock(Illuminate\Console\Command::class);
    $command->shouldReceive('line')->twice();
    $seeder->setCommandForTests($command);

    $seeder->runLogOperation(SeedersRelationTagStubModel::class);
    SeedersRelationTagStubModel::query()->create(['name' => 'already']);
    $seeder->runLogOperation(SeedersRelationTagStubModel::class);
});
