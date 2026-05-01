<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\HasTranslationsFactory;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;


beforeEach(function (): void {
    Schema::create('fake_translatable_models', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->json('components')->nullable();
        $table->timestamps();
    });

    Schema::create('fake_translatable_model_translations', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('fake_translatable_model_id')->constrained('fake_translatable_models')->cascadeOnDelete();
        $table->string('locale');
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->json('components')->nullable();
        $table->timestamps();
    });
});

it('duplicates default translation into random locales, preserving components and allowing overrides', function (): void {
    config(['app.locale' => 'en', 'app.available_locales' => ['en', 'it', 'fr']]);

    $components = [
        'body' => [
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => 'Hello']],
            ],
        ],
        'meta' => [
            'tags' => ['a', 'b'],
        ],
    ];

    $model = FakeTranslatableModel::query()->create([]);
    $model->setTranslation('en', [
        'title' => 'Default Title',
        'slug' => 'default-slug',
        'components' => $components,
    ]);
    $model->save();

    $duplicator = new class
    {
        use HasTranslationsFactory;
    };

    $duplicator->createTranslations($model, static fn (string $locale): array => [
        'title' => 'Title_' . $locale,
    ]);

    $translations = $model->translations()->get()->keyBy('locale');

    expect($translations)->toHaveKey('en');
    expect($translations->count())->toBeGreaterThan(1);

    $translations->each(function ($translation, string $locale) use ($components): void {
        if ($locale === 'en') {
            expect($translation->title)->toBe('Default Title');

            return;
        }

        expect($translation->components)->toEqual($components);
        expect($translation->title)->toBe('Title_' . $locale);
        expect($translation->slug)->toBe('default-slug');
    });
});

it('throws when the default translation is missing', function (): void {
    config(['app.locale' => 'en', 'app.available_locales' => ['en', 'it']]);

    $model = FakeTranslatableModel::query()->create([]);

    $duplicator = new class
    {
        use HasTranslationsFactory;
    };

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessageMatches('/Default translation not found/');

    $duplicator->createTranslations($model, static fn (): array => []);
});
