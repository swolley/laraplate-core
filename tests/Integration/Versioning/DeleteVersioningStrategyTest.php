<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Models\Version;
use Modules\Core\Tests\Stubs\Versioning\SoftDeletedVersionedArticle;
use Modules\Core\Tests\Stubs\Versioning\VersionedArticle;
use Overtrue\LaravelVersionable\VersionStrategy;

beforeEach(function (): void {
    config()->set('versionable.version_model', Version::class);

    Schema::create(VersionedArticle::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function (): void {
    Schema::dropIfExists(VersionedArticle::TABLE);
});

it('records a hard delete as a self-contained snapshot tombstone', function (): void {
    $article = SoftDeletedVersionedArticle::query()->create(['title' => 'First']);

    $article->forceDelete();

    $tombstone = Version::query()->latest('id')->firstOrFail();

    expect($tombstone->change_type)->toBe(VersionChangeType::Deleted)
        ->and($tombstone->version_strategy)->toBe(VersionStrategy::SNAPSHOT)
        ->and($tombstone->original_contents)->toBe(['title' => 'First'])
        ->and($tombstone->contents)->toBe([]);
});

it('records a soft delete as an update of deleted_at, not a Deleted row', function (): void {
    $article = SoftDeletedVersionedArticle::query()->create(['title' => 'First']);

    $article->delete();

    $trashing = Version::query()->latest('id')->firstOrFail();

    expect($trashing->change_type)->toBe(VersionChangeType::Updated)
        ->and($trashing->original_contents)->toBe(['deleted_at' => null])
        ->and($trashing->contents)->toHaveKey('deleted_at')
        ->and($trashing->contents['deleted_at'])->not->toBeNull();
});

it('exposes trashing versions and excludes the restore that clears deleted_at', function (): void {
    $article = SoftDeletedVersionedArticle::query()->create(['title' => 'First']);
    $article->delete();
    $trashing_version_id = Version::query()->latest('id')->firstOrFail()->getKey();
    $article->restore();

    $trashing_versions = $article->trashingVersions()->get();

    expect($trashing_versions)->toHaveCount(1)
        ->and($trashing_versions->first()->getKey())->toBe($trashing_version_id);
});
