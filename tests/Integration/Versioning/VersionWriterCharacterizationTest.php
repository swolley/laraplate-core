<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Version;
use Modules\Core\Tests\Stubs\Versioning\VersionedArticle;
use Overtrue\LaravelVersionable\VersionStrategy;

beforeEach(function (): void {
    config()->set('versionable.version_model', Version::class);

    Schema::create(VersionedArticle::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists(VersionedArticle::TABLE);
});

/**
 * Current production persistence entrypoints to migrate to the sole writer:
 *
 * - [ ] HasVersions::createVersion()
 * - [ ] HasVersions::createInitialVersion()
 * - [ ] VersioningService::createVersion()
 * - [ ] Version::createForModel()
 * - [ ] Overtrue\LaravelVersionable\Versionable lifecycle callbacks
 */
it('uses the Core writer for the initial lifecycle version', function (): void {
    $article = VersionedArticle::query()->create(['title' => 'First']);

    $versions = $article->versions()->orderBy('id')->get();
    $initial_version = $versions->first();

    expect($versions)->toHaveCount(1)
        ->and($initial_version)->toBeInstanceOf(Version::class)
        ->and($initial_version->contents)->toMatchArray(['title' => 'First'])
        ->and($initial_version->original_contents)->toMatchArray(['title' => 'First'])
        ->and($initial_version->version_strategy)->toBe(VersionStrategy::SNAPSHOT);
});

it('characterizes the vendor updated callback bypassing the Core writer', function (): void {
    $article = VersionedArticle::query()->create(['title' => 'First']);
    $version_count_before_update = $article->versions()->count();
    $vendor_writer_attributes = null;

    Version::creating(static function (Version $version) use (&$vendor_writer_attributes): void {
        if ($version->versionable_type !== VersionedArticle::class) {
            return;
        }

        $vendor_writer_attributes = $version->getAttributes();

        // The vendor writer omits this required Core attribute. Supply it only so
        // the insert reaches the assertions that characterize the split path.
        if (! array_key_exists('version_strategy', $vendor_writer_attributes)) {
            $version->version_strategy = VersionStrategy::DIFF;
        }
    });

    $article->update(['title' => 'Second']);

    $versions = $article->versions()->orderBy('id')->get();
    $updated_version = $versions->last();

    expect($versions->count() - $version_count_before_update)->toBe(1)
        ->and($updated_version)->toBeInstanceOf(Version::class)
        ->and($updated_version->contents)->toMatchArray(['title' => 'Second'])
        ->and($updated_version->original_contents)->toBeNull()
        ->and($vendor_writer_attributes)->not->toHaveKey('original_contents')
        ->and($vendor_writer_attributes)->not->toHaveKey('version_strategy');
});
