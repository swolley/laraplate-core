<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Version;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationRoot;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationSubject;

beforeEach(function (): void {
    config()->set('versionable.version_model', Version::class);

    Schema::create(VersionedRelationRoot::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });
    Schema::create(VersionedRelationSubject::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create(VersionedRelationRoot::PIVOT_TABLE, function (Blueprint $table): void {
        $table->unsignedBigInteger('root_id');
        $table->unsignedBigInteger('subject_id');
        $table->unsignedInteger('position')->default(0);
    });
});

afterEach(function (): void {
    Schema::dropIfExists(VersionedRelationRoot::PIVOT_TABLE);
    Schema::dropIfExists(VersionedRelationSubject::TABLE);
    Schema::dropIfExists(VersionedRelationRoot::TABLE);
});

it('syncs membership to a target set as one revision', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);
    $a = VersionedRelationSubject::query()->create(['name' => 'A']);
    $b = VersionedRelationSubject::query()->create(['name' => 'B']);
    $c = VersionedRelationSubject::query()->create(['name' => 'C']);

    $root->attachVersioned('categories', $a->getKey(), ['position' => 1]);
    $root->attachVersioned('categories', $b->getKey(), ['position' => 2]);

    $root->syncVersioned('categories', [
        $b->getKey() => ['position' => 9],
        $c->getKey() => ['position' => 3],
    ]);

    $membership = collect($root->versionedRelationMembership('categories'))->keyBy('id');

    expect($root->categories()->count())->toBe(2)
        ->and($membership->keys()->sort()->values()->all())
        ->toBe(collect([$b->getKey(), $c->getKey()])->sort()->values()->all())
        ->and($membership[$b->getKey()]['pivot'])->toBe(['position' => 9])
        ->and($membership[$c->getKey()]['pivot'])->toBe(['position' => 3]);

    // The whole sync is one version set (one revision): detach A + upsert B + attach C = 3 rows.
    $last_set_id = Version::query()->where('relation_path', 'categories')->max('version_set_id');

    expect(Version::query()->where('version_set_id', $last_set_id)->where('relation_path', 'categories')->count())->toBe(3);
});

it('syncing to an empty target detaches every member', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);
    $a = VersionedRelationSubject::query()->create(['name' => 'A']);
    $root->attachVersioned('categories', $a->getKey(), ['position' => 1]);

    $root->syncVersioned('categories', []);

    expect($root->categories()->count())->toBe(0)
        ->and($root->versionedRelationMembership('categories'))->toBe([]);
});
