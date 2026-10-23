<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Enums\VersionStrategy;
use Modules\Core\Models\Version;
use Modules\Core\Tests\Stubs\Versioning\TranslatedDocument;
use Modules\Core\Tests\Stubs\Versioning\TranslatedDocumentTranslation;

/**
 * A translation has no history of its own: its changes are rows of the owner's history, one per locale, with the
 * weight of the owner's strategy (D10, D11, D28); the first row of a locale is a complete image (D45 rule 3).
 */
beforeEach(function (): void {
    config()->set('versionable.version_model', Version::class);

    Schema::create(TranslatedDocument::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->unsignedInteger('lock_version')->default(1);
        $table->timestamps();
    });

    Schema::create(TranslatedDocumentTranslation::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->foreignId('translated_document_id')->constrained(TranslatedDocument::TABLE)->cascadeOnDelete();
        $table->string('locale');
        $table->string('title')->nullable();
        $table->string('body')->nullable();
        $table->timestamps();
    });

    $this->document = TranslatedDocument::query()->create(['code' => 'doc']);
});

afterEach(function (): void {
    Schema::dropIfExists(TranslatedDocumentTranslation::TABLE);
    Schema::dropIfExists(TranslatedDocument::TABLE);
});

function locale_rows(TranslatedDocument $document, string $locale): Illuminate\Support\Collection
{
    return $document->versions()->reorder('id')->get()
        ->filter(static fn (Version $row): bool => $row->relation_path === 'translations' && ($row->subject_key['locale'] ?? null) === $locale)
        ->values();
}

it('records the creation of a locale on the owner as a complete image read back', function (): void {
    $this->document->setTranslation('it', ['title' => 'Titolo', 'body' => 'Testo']);

    $row = locale_rows($this->document, 'it')->sole();

    expect($row->change_type)->toBe(VersionChangeType::Created)
        ->and($row->version_strategy)->toBe(VersionStrategy::SNAPSHOT)
        ->and($row->contents)->toBe(['title' => 'Titolo', 'body' => 'Testo'])
        ->and($row->versionable_id)->toBe($this->document->getKey());
});

it('records a change of a locale as the changed fields for a DIFF owner', function (): void {
    $this->document->setTranslation('it', ['title' => 'Titolo', 'body' => 'Testo']);
    $this->document->setTranslation('it', ['title' => 'Nuovo titolo', 'body' => 'Testo']);

    $row = locale_rows($this->document, 'it')->last();

    expect($row->change_type)->toBe(VersionChangeType::Updated)
        ->and($row->version_strategy)->toBe(VersionStrategy::DIFF)
        ->and($row->contents)->toBe(['title' => 'Nuovo titolo'])
        ->and($row->original_contents)->toBe(['title' => 'Titolo']);
});

it('records a change of a locale as the full image for a SNAPSHOT owner', function (): void {
    $this->document->useVersionStrategy(VersionStrategy::SNAPSHOT);
    $this->document->setTranslation('it', ['title' => 'Titolo', 'body' => 'Testo']);
    $this->document->setTranslation('it', ['title' => 'Nuovo titolo', 'body' => 'Testo']);

    $row = locale_rows($this->document, 'it')->last();

    expect($row->version_strategy)->toBe(VersionStrategy::SNAPSHOT)
        ->and($row->contents)->toBe(['title' => 'Nuovo titolo', 'body' => 'Testo']);
});

it('records the removal of a locale with its image', function (): void {
    $this->document->setTranslation('it', ['title' => 'Titolo', 'body' => 'Testo']);

    $this->document->translations()->where('locale', 'it')->firstOrFail()->delete();

    $row = locale_rows($this->document, 'it')->last();

    expect($row->change_type)->toBe(VersionChangeType::Deleted)
        ->and($row->original_contents)->toBe(['title' => 'Titolo', 'body' => 'Testo'])
        ->and($row->contents)->toBe([]);
});

it('records nothing when the owner is not versioned', function (): void {
    $this->document->useVersionStrategy(VersionStrategy::DIFF);
    TranslatedDocument::disableVersioning();

    try {
        $this->document->setTranslation('it', ['title' => 'Titolo']);
    } finally {
        TranslatedDocument::enableVersioning();
    }

    expect(locale_rows($this->document, 'it'))->toHaveCount(0);
});

it('writes no row on the translation itself', function (): void {
    $this->document->setTranslation('it', ['title' => 'Titolo']);

    expect(Version::query()->where('versionable_type', (new TranslatedDocumentTranslation)->getMorphClass())->count())->toBe(0);
});
