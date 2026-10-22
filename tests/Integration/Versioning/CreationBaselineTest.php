<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\VersionSetKind;
use Modules\Core\Models\Version;
use Modules\Core\Models\VersionSet;
use Modules\Core\Tests\Stubs\Versioning\VersionedDocument;
use Modules\Core\Versioning\Contracts\VersionSetManagerInterface;
use Modules\Core\Versioning\Data\VersionSetOptions;
use Modules\Core\Versioning\Data\VersionSetRoot;

/**
 * The set that holds the creation of a root is stored as a baseline, a complete anchor (D53); the scope keeps
 * its ordinary options, and a revert set never becomes one (D60).
 */
beforeEach(function (): void {
    config()->set('versionable.version_model', Version::class);

    Schema::create(VersionedDocument::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function (): void {
    Schema::dropIfExists(VersionedDocument::TABLE);
});

/**
 * @return list<VersionSetKind>
 */
function stored_set_kinds(): array
{
    return VersionSet::query()->orderBy('id')->pluck('kind')->all();
}

it('stores the creation set of a root as a baseline and later sets as changes', function (): void {
    $document = VersionedDocument::query()->create(['title' => 'Before']);
    $document->update(['title' => 'After']);

    expect(stored_set_kinds())->toBe([VersionSetKind::Baseline, VersionSetKind::Change]);
});

it('lets a nested run with ordinary options join a set stored as a baseline', function (): void {
    $document = new VersionedDocument(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(VersionSetRoot::forModel($document), function () use ($document, $manager): void {
        $document->save();
        $manager->run(
            VersionSetRoot::forModel($document),
            fn () => $document->update(['title' => 'After']),
            new VersionSetOptions,
        );
    });

    expect(stored_set_kinds())->toBe([VersionSetKind::Baseline])
        ->and(Version::query()->count())->toBe(2);
});

it('never stores a revert set as a baseline', function (): void {
    $document = VersionedDocument::query()->create(['title' => 'Before']);
    $created = $document->versions()->firstOrFail();
    $document->update(['title' => 'After']);

    $document->revertToVersion($created->getKey());

    expect(stored_set_kinds())->toBe([VersionSetKind::Baseline, VersionSetKind::Change, VersionSetKind::Revert]);
});
