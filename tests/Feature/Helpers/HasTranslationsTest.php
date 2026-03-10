<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class, RefreshDatabase::class);

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

describe('initializeHasTranslations', function (): void {
    it('adds translation to hidden attributes', function (): void {
        $model = new FakeTranslatableModel();
        $model->initializeHasTranslations();

        expect($model->getHidden())->toContain('translation');
    });

    it('adds translation to with attributes', function (): void {
        $model = new FakeTranslatableModel();
        $model->initializeHasTranslations();

        $reflection = new ReflectionClass($model);
        $property = $reflection->getProperty('with');

        expect($property->getValue($model))->toContain('translation');
    });

    it('adds locale to appends attributes', function (): void {
        $model = new FakeTranslatableModel();
        $model->initializeHasTranslations();

        expect($model->getAppends())->toContain('locale');
    });
});

describe('getTranslatableFields', function (): void {
    it('returns translatable fields for model', function (): void {
        $fields = FakeTranslatableModel::getTranslatableFields();

        expect($fields)->toBeArray();
        expect($fields)->toContain('title', 'slug', 'components');
    });

    it('caches translatable fields per model class', function (): void {
        $fields1 = FakeTranslatableModel::getTranslatableFields();
        $fields2 = FakeTranslatableModel::getTranslatableFields();

        // Should be the same (cached)
        expect($fields1)->toBe($fields2);
    });

    it('excludes locale and foreign keys from translatable fields', function (): void {
        $fields = FakeTranslatableModel::getTranslatableFields();

        expect($fields)->not->toContain('locale');
        expect($fields)->not->toContain('fake_translatable_model_id');
    });
});

describe('isTranslatableField', function (): void {
    it('returns true for translatable fields', function (): void {
        $model = new FakeTranslatableModel();

        expect($model->isTranslatableField('title'))->toBeTrue();
        expect($model->isTranslatableField('components'))->toBeTrue();
    });

    it('returns false for non-translatable fields', function (): void {
        $model = new FakeTranslatableModel();

        expect($model->isTranslatableField('id'))->toBeFalse();
        expect($model->isTranslatableField('created_at'))->toBeFalse();
        expect($model->isTranslatableField('nonexistent'))->toBeFalse();
    });
});

describe('translations relation', function (): void {
    it('returns HasMany relation', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $relation = $model->translations();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('can create multiple translations', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        // Use three distinct locales so we get 3 rows (app locale may be 'en', so avoid default vs 'en' overlap)
        $model->setTranslation('it', ['title' => 'Italian Title']);
        $model->setTranslation('en', ['title' => 'English Title']);
        $model->setTranslation('fr', ['title' => 'French Title']);
        $model->save();

        expect($model->translations)->toHaveCount(3);
    });
});

describe('translation relation', function (): void {
    it('returns HasOne relation', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $relation = $model->translation();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasOne::class);
    });

    it('returns translation for current locale', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        // Use explicit locales so we have two distinct rows (avoid default_locale === 'en' overwriting)
        $model->setTranslation('it', ['title' => 'Current Locale Title']);
        $model->setTranslation('en', ['title' => 'English Title']);
        $model->save();
        // Set context and force fresh load so relation uses current locale (cached one may be from save())
        LocaleContext::set('it');
        $model->unsetRelation('translation');
        $translation = $model->translation;
        expect($translation)->not->toBeNull();
        expect($translation->locale)->toBe('it');
        expect($translation->title)->toBe('Current Locale Title');
    });
});

describe('getTranslation', function (): void {
    it('returns translation for specific locale', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        // Use explicit locales so default (e.g. 'en') does not collide with 'en'
        $model->setTranslation('it', [
            'title' => 'Italian Title',
            'slug' => 'italian-slug',
        ]);
        $model->setTranslation('en', [
            'title' => 'English Title',
            'slug' => 'english-slug',
        ]);
        $model->save();

        $it_translation = $model->getTranslation('it');
        $en_translation = $model->getTranslation('en');

        expect($it_translation->title)->toBe('Italian Title');
        expect($en_translation->title)->toBe('English Title');
    });

    it('returns translation for specific locale with another locale', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Italian Title']);
        $model->setTranslation('en', ['title' => 'English Title']);
        $model->save();

        $enTranslation = $model->getTranslation('en');
        expect($enTranslation)->not->toBeNull();
        expect($enTranslation->title)->toBe('English Title');
    });

    it('returns null when translation does not exist and fallback disabled', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Italian Title']);
        $model->save();

        $frTranslation = $model->getTranslation('fr', false);

        expect($frTranslation)->toBeNull();
    });

    it('falls back to default locale when translation missing and fallback enabled', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Default Title']);
        $model->save();

        $frTranslation = $model->getTranslation('fr', true);

        expect($frTranslation)->not->toBeNull();
        expect($frTranslation->locale)->toBe($default_locale);
        expect($frTranslation->title)->toBe('Default Title');
    });
});

describe('setTranslation and updateTranslation', function (): void {
    it('can set and get translation', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, [
            'title' => 'Test Title',
            'slug' => 'test-slug',
        ]);
        $model->save();

        expect($model->title)->toBe('Test Title');
        expect($model->slug)->toBe('test-slug');
    });

    it('creates new translation when it does not exist', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'New Title']);
        $model->save();

        expect($model->hasTranslation($default_locale))->toBeTrue();
        expect($model->title)->toBe('New Title');
    });

    it('updates existing translation', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Original Title']);
        $model->save();

        $model->setTranslation($default_locale, ['title' => 'Updated Title']);
        $model->save();

        expect($model->title)->toBe('Updated Title');
    });

    it('can update existing translation', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, [
            'title' => 'Original Title',
            'slug' => 'original-slug',
        ]);
        $model->save();

        $model->updateTranslation($default_locale, [
            'title' => 'Updated Title',
        ]);
        $model->save();

        expect($model->title)->toBe('Updated Title');
        expect($model->slug)->toBe('original-slug'); // Should remain unchanged
    });

    it('updateTranslation is alias for setTranslation', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Original Title']);
        $model->save();

        $model->updateTranslation($default_locale, ['title' => 'Updated Title']);
        $model->save();

        expect($model->title)->toBe('Updated Title');
    });

    it('reloads translation relation when setting current locale', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'New Title']);
        $model->save();

        // Translation should be loaded
        expect($model->getRelationValue('translation'))->not->toBeNull();
    });
});

describe('hasTranslation', function (): void {
    it('can check if translation exists', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        expect($model->hasTranslation($default_locale))->toBeFalse();
        expect($model->hasTranslation('fr'))->toBeFalse();
    });

    it('returns true when translation exists', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Test Title']);
        $model->save();

        expect($model->hasTranslation($default_locale))->toBeTrue();
    });

    it('returns false when translation does not exist', function (): void {
        $model = FakeTranslatableModel::query()->create([]);

        expect($model->hasTranslation('fr'))->toBeFalse();
    });

    it('uses current locale when locale not provided', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Test Title']);
        $model->save();

        expect($model->hasTranslation())->toBeTrue();
    });
});

describe('getAllTranslations', function (): void {
    it('can get all translations', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $model->setTranslation('it', ['title' => 'Italian Title']);
        $model->setTranslation('en', ['title' => 'English Title']);
        $model->setTranslation('fr', ['title' => 'French Title']);
        $model->save();

        $all_translations = $model->getAllTranslations();

        expect($all_translations)->toHaveCount(3);
        expect($all_translations->pluck('locale')->toArray())->toContain('it', 'en', 'fr');
    });

    it('returns all translations for model', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $model->setTranslation('it', ['title' => 'Italian Title']);
        $model->setTranslation('en', ['title' => 'English Title']);
        $model->setTranslation('fr', ['title' => 'French Title']);
        $model->save();

        $allTranslations = $model->getAllTranslations();

        expect($allTranslations)->toHaveCount(3);
        expect($allTranslations->pluck('locale')->toArray())->toContain('it', 'en', 'fr');
    });
});

describe('__get and __set for translatable fields', function (): void {
    it('can access translatable fields transparently', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, [
            'title' => 'Test Title',
            'slug' => 'test-slug',
        ]);
        $model->save();

        // Access as property
        expect($model->title)->toBe('Test Title');
        expect($model->slug)->toBe('test-slug');

        // Should appear in toArray
        $array = $model->toArray();
        expect($array['title'])->toBe('Test Title');
        expect($array['slug'])->toBe('test-slug');
    });

    it('can get translatable field value via property access', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Test Title']);
        $model->save();

        expect($model->title)->toBe('Test Title');
    });

    it('can set translatable fields transparently', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        // Set as property
        $model->title = 'New Title';
        $model->slug = 'new-slug';
        $model->save();

        expect($model->title)->toBe('New Title');
        expect($model->slug)->toBe('new-slug');

        // Verify it's saved in translation
        $translation = $model->getTranslation($default_locale);
        expect($translation->title)->toBe('New Title');
        expect($translation->slug)->toBe('new-slug');
    });

    it('can set translatable field value via property access', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->title = 'New Title';
        $model->save();

        expect($model->title)->toBe('New Title');
        expect($model->getTranslation($default_locale)->title)->toBe('New Title');
    });

    it('returns null for non-existent translatable field', function (): void {
        $model = FakeTranslatableModel::query()->create([]);

        // Field not set yet
        expect($model->title)->toBeNull();
    });
});

describe('setAttribute for translatable fields', function (): void {
    it('handles translatable fields via setAttribute', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setAttribute('title', 'Attribute Title');
        $model->save();

        expect($model->title)->toBe('Attribute Title');
        expect($model->getTranslation($default_locale)->title)->toBe('Attribute Title');
    });
});

describe('toArray', function (): void {
    it('includes translatable fields in toArray', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Array Title']);
        $model->save();

        $array = $model->toArray();

        expect($array)->toHaveKey('title');
        expect($array['title'])->toBe('Array Title');
    });

    it('includes locale in toArray', function (): void {
        $model = new FakeTranslatableModel();
        $model->initializeHasTranslations();

        $array = $model->toArray();

        expect($array)->toHaveKey('locale');
    });

    it('includes pending translations in toArray', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->title = 'Pending Title';

        $array = $model->toArray();

        expect($array['title'])->toBe('Pending Title');
    });
});

describe('inLocale', function (): void {
    it('can use inLocale to set translation for specific locale', function (): void {
        $model = FakeTranslatableModel::query()->create([]);

        $model->inLocale('en')->title = 'English Title';
        $model->inLocale('en')->slug = 'english-slug';
        $model->save();

        expect($model->getTranslation('en')->title)->toBe('English Title');
        expect($model->getTranslation('en')->slug)->toBe('english-slug');
    });

    it('sets locale context for next assignments', function (): void {
        $model = FakeTranslatableModel::query()->create([]);

        $model->inLocale('en')->title = 'English Title';
        $model->inLocale('fr')->title = 'French Title';
        $model->save();

        expect($model->getTranslation('en')->title)->toBe('English Title');
        expect($model->getTranslation('fr')->title)->toBe('French Title');
    });

    it('returns self for method chaining', function (): void {
        $model = FakeTranslatableModel::query()->create([]);

        $result = $model->inLocale('en');

        expect($result)->toBe($model);
    });
});

describe('pending_translations', function (): void {
    it('saves pending translations on save', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->title = 'Pending Title';
        // Not saved yet
        expect($model->hasTranslation($default_locale))->toBeFalse();

        $model->save();

        // Now should be saved
        expect($model->hasTranslation($default_locale))->toBeTrue();
        expect($model->title)->toBe('Pending Title');
    });

    it('clears pending translations after save', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->title = 'Pending Title';
        $model->save();

        // Pending translations should be cleared
        $reflection = new ReflectionClass($model);
        $property = $reflection->getProperty('pending_translations');

        expect($property->getValue($model))->toBe([]);
    });
});

describe('getTranslationModelClass', function (): void {
    it('resolves correct translation model class', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getTranslationModelClass');

        $translationClass = $method->invoke(null);

        expect($translationClass)->toBe(\Modules\Core\Tests\Fixtures\FakeTranslatableModelTranslation::class);
    });
});

describe('forLocale scope', function (): void {
    it('filters models by locale', function (): void {
        $model1 = FakeTranslatableModel::query()->create([]);
        $model2 = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model1->setTranslation($default_locale, ['title' => 'Italian Item']);
        $model1->setTranslation('en', ['title' => 'English Item']);
        $model1->save();

        $model2->setTranslation('en', ['title' => 'English Item 2']);
        $model2->save();

        // Filter by English locale (scope method)
        $englishItems = FakeTranslatableModel::query()->forLocale('en')->get();

        expect($englishItems)->toHaveCount(2);
        expect($englishItems->pluck('id')->toArray())->toContain($model1->id, $model2->id);
    });

    it('removes default locale scope when using forLocale', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Default Item']);
        $model->setTranslation('en', ['title' => 'English Item']);
        $model->save();

        // Should be able to query for any locale, not just default
        $enItems = FakeTranslatableModel::query()->forLocale('en')->get();

        expect($enItems)->toHaveCount(1);
    });
});

describe('withTranslation scope', function (): void {
    it('eager loads translation without filtering', function (): void {
        $model1 = FakeTranslatableModel::query()->create([]);
        $model2 = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model1->setTranslation($default_locale, ['title' => 'Item 1']);
        $model1->setTranslation('en', ['title' => 'Item 1 EN']);
        $model1->save();

        $model2->setTranslation($default_locale, ['title' => 'Item 2']);
        $model2->save();

        // Should load all items but with English translation if available
        $items = FakeTranslatableModel::query()->withTranslation('en')->get();

        expect($items)->toHaveCount(2);
        expect($items->first()->getRelationValue('translation'))->not->toBeNull();
    });
});

describe('Integration with different models', function (): void {
    it('works with a translatable model', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, [
            'title' => 'Test Title',
            'slug' => 'test-slug',
        ]);
        $model->save();

        expect($model->title)->toBe('Test Title');
        expect($model->slug)->toBe('test-slug');
    });

    it('works with another translatable instance', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, [
            'title' => 'Test Item',
            'slug' => 'test-item',
        ]);
        $model->save();

        expect($model->title)->toBe('Test Item');
        expect($model->slug)->toBe('test-item');
    });

    it('works with a third translatable instance', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, [
            'title' => 'Test Tag',
            'slug' => 'test-tag',
        ]);
        $model->save();

        expect($model->title)->toBe('Test Tag');
        expect($model->slug)->toBe('test-tag');
    });

    it('can handle components translation', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $components = [
            'body' => 'Test body',
            'excerpt' => 'Test excerpt',
        ];
        $model->setTranslation($default_locale, [
            'title' => 'Test Title',
            'slug' => 'test-slug',
            'components' => $components,
        ]);
        $model->save();

        expect($model->title)->toBe('Test Title');
        expect($model->components)->toEqual($components);
    });
});

describe('Fallback behavior', function (): void {
    it('can handle fallback when translation missing', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        // Set only default translation
        $model->setTranslation($default_locale, [
            'title' => 'Default Title',
            'slug' => 'default-slug',
        ]);
        $model->save();

        // Try to access with different locale (should fallback to default if enabled)
        $original_locale = LocaleContext::get();
        LocaleContext::set('fr');

        // If fallback is enabled, should get default, otherwise null
        $title = $model->title;
        expect($title)->not->toBeNull();

        // Restore original locale
        LocaleContext::set($original_locale);
    });

    it('uses fallback when enabled and translation missing', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Default Title']);
        $model->save();

        // Test with fallback enabled
        $originalLocale = LocaleContext::get();
        LocaleContext::set('fr');

        // Should fallback to default when fallback is enabled
        $title = $model->getTranslation('fr', true)?->title ?? $model->title;

        expect($title)->toBe('Default Title');

        LocaleContext::set($originalLocale);
    });

    it('returns null when fallback disabled and translation missing', function (): void {
        $model = FakeTranslatableModel::query()->create([]);
        $default_locale = config('app.locale');

        $model->setTranslation($default_locale, ['title' => 'Default Title']);
        $model->save();

        // Test with fallback disabled
        $originalLocale = LocaleContext::get();
        LocaleContext::set('fr');

        // Should return null when fallback is disabled
        $translation = $model->getTranslation('fr', false);
        expect($translation)->toBeNull();

        LocaleContext::set($originalLocale);
    });
});
