<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Models\Version;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationRoot;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationSubject;
use Overtrue\LaravelVersionable\VersionStrategy;

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

it('records an attach as a Created membership version row', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);
    $subject = VersionedRelationSubject::query()->create(['name' => 'News']);

    $root->attachVersioned('categories', $subject->getKey(), ['position' => 2]);

    $membership = Version::query()->latest('id')->firstOrFail();

    expect($root->categories()->count())->toBe(1)
        ->and($membership->change_type)->toBe(VersionChangeType::Created)
        ->and($membership->relation_path)->toBe('categories')
        ->and($membership->subject_key)->toBe(['id' => $subject->getKey()])
        ->and($membership->contents)->toBe(['position' => 2])
        ->and($membership->version_strategy)->toBe(VersionStrategy::SNAPSHOT);
});

it('records a detach as a Deleted membership version row', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);
    $subject = VersionedRelationSubject::query()->create(['name' => 'News']);
    $root->attachVersioned('categories', $subject->getKey(), ['position' => 2]);

    $root->detachVersioned('categories', $subject->getKey());

    $membership = Version::query()->latest('id')->firstOrFail();

    expect($root->categories()->count())->toBe(0)
        ->and($membership->change_type)->toBe(VersionChangeType::Deleted)
        ->and($membership->relation_path)->toBe('categories')
        ->and($membership->subject_key)->toBe(['id' => $subject->getKey()]);
});

it('re-attaching an existing subject updates the pivot in place without duplicating', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);
    $subject = VersionedRelationSubject::query()->create(['name' => 'News']);

    $root->attachVersioned('categories', $subject->getKey(), ['position' => 1]);
    $root->attachVersioned('categories', $subject->getKey(), ['position' => 5]);

    expect($root->categories()->count())->toBe(1)
        ->and((int) $root->categories()->first()->pivot->position)->toBe(5)
        ->and($root->versionedRelationMembership('categories'))->toBe([
            ['id' => $subject->getKey(), 'pivot' => ['position' => 5]],
        ]);
});

it('rejects an undeclared relation', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);

    expect(fn () => $root->attachVersioned('unknown', 1))->toThrow(InvalidArgumentException::class);
});
