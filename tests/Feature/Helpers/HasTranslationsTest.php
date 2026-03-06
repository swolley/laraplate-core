<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cms\Casts\EntityType;
use Modules\Cms\Casts\FieldType;
use Modules\Cms\Models\Category;
use Modules\Cms\Models\Content;
use Modules\Cms\Models\Contributor;
use Modules\Cms\Models\Entity;
use Modules\Cms\Models\Field;
use Modules\Cms\Models\Pivot\Presettable;
use Modules\Cms\Models\Preset;
use Modules\Cms\Models\Tag;
use Modules\Core\Helpers\LocaleContext;
uses(Tests\LaravelTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    foreach ([EntityType::CONTENTS, EntityType::CONTRIBUTORS, EntityType::CATEGORIES] as $entityType) {
        $entity = Entity::firstOrCreate(
            ['name' => $entityType->value],
            ['type' => $entityType],
        );

        $preset = Preset::firstOrCreate(
            ['entity_id' => $entity->id, 'name' => 'default'],
        );

        Presettable::firstOrCreate(
            ['entity_id' => $entity->id, 'preset_id' => $preset->id],
        );

        if ($preset->fields()->count() === 0) {
            $field = Field::create([
                'name' => 'description_' . uniqid(),
                'type' => FieldType::TEXT,
                'options' => new stdClass(),
            ]);
            $preset->fields()->attach($field->id, [
                'default' => null,
                'is_required' => false,
            ]);
        }
    }
});

describe('initializeHasTranslations', function (): void {
    it('adds translation to hidden attributes', function (): void {
        // Create instance using factory to ensure database is ready
        $contributor = Contributor::factory()->make();
        $contributor->initializeHasTranslations();

        expect($contributor->getHidden())->toContain('translation');
    });

    it('adds translation to with attributes', function (): void {
        // Create instance using factory to ensure database is ready
        $contributor = Contributor::factory()->make();
        $contributor->initializeHasTranslations();

        $reflection = new ReflectionClass($contributor);
        $property = $reflection->getProperty('with');
        $property->setAccessible(true);

        expect($property->getValue($contributor))->toContain('translation');
    });

    it('adds locale to appends attributes', function (): void {
        // Create instance using factory to ensure database is ready
        $contributor = Contributor::factory()->make();
        $contributor->initializeHasTranslations();

        expect($contributor->getAppends())->toContain('locale');
    });
});

describe('getTranslatableFields', function (): void {
    it('returns translatable fields for model', function (): void {
        $content = Content::factory()->create();
        $fields = $content::getTranslatableFields();

        expect($fields)->toBeArray();
        expect($fields)->toContain('title', 'slug', 'components');
    });

    it('returns translatable fields for Contributor model', function (): void {
        $contributor = Contributor::factory()->create();
        $fields = $contributor::getTranslatableFields();

        expect($fields)->toBeArray();
        expect($fields)->toContain('name', 'components');
    });

    it('caches translatable fields per model class', function (): void {
        $contributor1 = Contributor::factory()->create();
        $contributor2 = Contributor::factory()->create();

        $fields1 = $contributor1::getTranslatableFields();
        $fields2 = $contributor2::getTranslatableFields();

        // Should be the same (cached)
        expect($fields1)->toBe($fields2);
    });

    it('excludes locale and foreign keys from translatable fields', function (): void {
        $contributor = Contributor::factory()->create();
        $fields = $contributor::getTranslatableFields();

        expect($fields)->not->toContain('locale');
        expect($fields)->not->toContain('contributor_id');
    });
});

describe('isTranslatableField', function (): void {
    it('returns true for translatable fields', function (): void {
        $contributor = Contributor::factory()->create();

        expect($contributor->isTranslatableField('name'))->toBeTrue();
        expect($contributor->isTranslatableField('components'))->toBeTrue();
    });

    it('returns false for non-translatable fields', function (): void {
        $contributor = Contributor::factory()->create();

        expect($contributor->isTranslatableField('id'))->toBeFalse();
        expect($contributor->isTranslatableField('created_at'))->toBeFalse();
        expect($contributor->isTranslatableField('nonexistent'))->toBeFalse();
    });
});

describe('translations relation', function (): void {
    it('returns HasMany relation', function (): void {
        $contributor = Contributor::factory()->create();
        $relation = $contributor->translations();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('can create multiple translations', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Italian Name']);
        $contributor->setTranslation('en', ['name' => 'English Name']);
        $contributor->setTranslation('fr', ['name' => 'French Name']);
        $contributor->save();

        expect($contributor->translations)->toHaveCount(3);
    });
});

describe('translation relation', function (): void {
    it('returns HasOne relation', function (): void {
        $contributor = Contributor::factory()->create();
        $relation = $contributor->translation();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasOne::class);
    });

    it('returns translation for current locale', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Current Locale Name']);
        $contributor->setTranslation('en', ['name' => 'English Name']);
        $contributor->save();

        $translation = $contributor->translation;
        expect($translation)->not->toBeNull();
        expect($translation->locale)->toBe($default_locale);
        expect($translation->name)->toBe('Current Locale Name');
    });
});

describe('getTranslation', function (): void {
    it('returns translation for specific locale', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        $content->setTranslation($default_locale, [
            'title' => 'Italian Title',
            'slug' => 'italian-slug',
        ]);
        $content->setTranslation('en', [
            'title' => 'English Title',
            'slug' => 'english-slug',
        ]);
        $content->save();

        $it_translation = $content->getTranslation($default_locale);
        $en_translation = $content->getTranslation('en');

        expect($it_translation->title)->toBe('Italian Title');
        expect($en_translation->title)->toBe('English Title');
    });

    it('returns translation for specific locale with Contributor', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Italian Name']);
        $contributor->setTranslation('en', ['name' => 'English Name']);
        $contributor->save();

        $enTranslation = $contributor->getTranslation('en');
        expect($enTranslation)->not->toBeNull();
        expect($enTranslation->name)->toBe('English Name');
    });

    it('returns null when translation does not exist and fallback disabled', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Italian Name']);
        $contributor->save();

        $frTranslation = $contributor->getTranslation('fr', false);

        expect($frTranslation)->toBeNull();
    });

    it('falls back to default locale when translation missing and fallback enabled', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Default Name']);
        $contributor->save();

        $frTranslation = $contributor->getTranslation('fr', true);

        expect($frTranslation)->not->toBeNull();
        expect($frTranslation->locale)->toBe($default_locale);
        expect($frTranslation->name)->toBe('Default Name');
    });
});

describe('setTranslation and updateTranslation', function (): void {
    it('can set and get translation', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        $content->setTranslation($default_locale, [
            'title' => 'Test Title',
            'slug' => 'test-slug',
        ]);
        $content->save();

        expect($content->title)->toBe('Test Title');
        expect($content->slug)->toBe('test-slug');
    });

    it('creates new translation when it does not exist', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'New Name']);
        $contributor->save();

        expect($contributor->hasTranslation($default_locale))->toBeTrue();
        expect($contributor->name)->toBe('New Name');
    });

    it('updates existing translation', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Original Name']);
        $contributor->save();

        $contributor->setTranslation($default_locale, ['name' => 'Updated Name']);
        $contributor->save();

        expect($contributor->name)->toBe('Updated Name');
    });

    it('can update existing translation', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        $content->setTranslation($default_locale, [
            'title' => 'Original Title',
            'slug' => 'original-slug',
        ]);
        $content->save();

        $content->updateTranslation($default_locale, [
            'title' => 'Updated Title',
        ]);
        $content->save();

        expect($content->title)->toBe('Updated Title');
        expect($content->slug)->toBe('original-slug'); // Should remain unchanged
    });

    it('updateTranslation is alias for setTranslation', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Original Name']);
        $contributor->save();

        $contributor->updateTranslation($default_locale, ['name' => 'Updated Name']);
        $contributor->save();

        expect($contributor->name)->toBe('Updated Name');
    });

    it('reloads translation relation when setting current locale', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'New Name']);
        $contributor->save();

        // Translation should be loaded
        expect($contributor->getRelationValue('translation'))->not->toBeNull();
    });
});

describe('hasTranslation', function (): void {
    it('can check if translation exists', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        expect($content->hasTranslation($default_locale))->toBeTrue();
        expect($content->hasTranslation('fr'))->toBeFalse();
    });

    it('returns true when translation exists', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Test Name']);
        $contributor->save();

        expect($contributor->hasTranslation($default_locale))->toBeTrue();
    });

    it('returns false when translation does not exist', function (): void {
        $contributor = Contributor::factory()->create();

        expect($contributor->hasTranslation('fr'))->toBeFalse();
    });

    it('uses current locale when locale not provided', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Test Name']);
        $contributor->save();

        expect($contributor->hasTranslation())->toBeTrue();
    });
});

describe('getAllTranslations', function (): void {
    it('can get all translations', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        $content->setTranslation($default_locale, ['title' => 'Italian Title']);
        $content->setTranslation('en', ['title' => 'English Title']);
        $content->setTranslation('fr', ['title' => 'French Title']);
        $content->save();

        $all_translations = $content->getAllTranslations();

        expect($all_translations)->toHaveCount(3);
        expect($all_translations->pluck('locale')->toArray())->toContain($default_locale, 'en', 'fr');
    });

    it('returns all translations for model', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Italian Name']);
        $contributor->setTranslation('en', ['name' => 'English Name']);
        $contributor->setTranslation('fr', ['name' => 'French Name']);
        $contributor->save();

        $allTranslations = $contributor->getAllTranslations();

        expect($allTranslations)->toHaveCount(3);
        expect($allTranslations->pluck('locale')->toArray())->toContain($default_locale, 'en', 'fr');
    });
});

describe('__get and __set for translatable fields', function (): void {
    it('can access translatable fields transparently', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        $content->setTranslation($default_locale, [
            'title' => 'Test Title',
            'slug' => 'test-slug',
        ]);
        $content->save();

        // Access as property
        expect($content->title)->toBe('Test Title');
        expect($content->slug)->toBe('test-slug');

        // Should appear in toArray
        $array = $content->toArray();
        expect($array['title'])->toBe('Test Title');
        expect($array['slug'])->toBe('test-slug');
    });

    it('can get translatable field value via property access', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Test Name']);
        $contributor->save();

        expect($contributor->name)->toBe('Test Name');
    });

    it('can set translatable fields transparently', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        // Set as property
        $content->title = 'New Title';
        $content->slug = 'new-slug';
        $content->save();

        expect($content->title)->toBe('New Title');
        expect($content->slug)->toBe('new-slug');

        // Verify it's saved in translation
        $translation = $content->getTranslation($default_locale);
        expect($translation->title)->toBe('New Title');
        expect($translation->slug)->toBe('new-slug');
    });

    it('can set translatable field value via property access', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->name = 'New Name';
        $contributor->save();

        expect($contributor->name)->toBe('New Name');
        expect($contributor->getTranslation($default_locale)->name)->toBe('New Name');
    });

    it('returns null for non-existent translatable field', function (): void {
        $contributor = Contributor::factory()->create();

        // Field not set yet
        expect($contributor->name)->toBeNull();
    });
});

describe('setAttribute for translatable fields', function (): void {
    it('handles translatable fields via setAttribute', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setAttribute('name', 'Attribute Name');
        $contributor->save();

        expect($contributor->name)->toBe('Attribute Name');
        expect($contributor->getTranslation($default_locale)->name)->toBe('Attribute Name');
    });
});

describe('toArray', function (): void {
    it('includes translatable fields in toArray', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Array Name']);
        $contributor->save();

        $array = $contributor->toArray();

        expect($array)->toHaveKey('name');
        expect($array['name'])->toBe('Array Name');
    });

    it('includes locale in toArray', function (): void {
        $contributor = Contributor::factory()->create();
        $contributor->initializeHasTranslations();

        $array = $contributor->toArray();

        expect($array)->toHaveKey('locale');
    });

    it('includes pending translations in toArray', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->name = 'Pending Name';

        $array = $contributor->toArray();

        expect($array['name'])->toBe('Pending Name');
    });
});

describe('inLocale', function (): void {
    it('can use inLocale to set translation for specific locale', function (): void {
        $content = Content::factory()->create();

        $content->inLocale('en')->title = 'English Title';
        $content->inLocale('en')->slug = 'english-slug';
        $content->save();

        expect($content->getTranslation('en')->title)->toBe('English Title');
        expect($content->getTranslation('en')->slug)->toBe('english-slug');
    });

    it('sets locale context for next assignments', function (): void {
        $contributor = Contributor::factory()->create();

        $contributor->inLocale('en')->name = 'English Name';
        $contributor->inLocale('fr')->name = 'French Name';
        $contributor->save();

        expect($contributor->getTranslation('en')->name)->toBe('English Name');
        expect($contributor->getTranslation('fr')->name)->toBe('French Name');
    });

    it('returns self for method chaining', function (): void {
        $contributor = Contributor::factory()->create();

        $result = $contributor->inLocale('en');

        expect($result)->toBe($contributor);
    });
});

describe('pending_translations', function (): void {
    it('saves pending translations on save', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->name = 'Pending Name';
        // Not saved yet
        expect($contributor->hasTranslation($default_locale))->toBeFalse();

        $contributor->save();

        // Now should be saved
        expect($contributor->hasTranslation($default_locale))->toBeTrue();
        expect($contributor->name)->toBe('Pending Name');
    });

    it('clears pending translations after save', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->name = 'Pending Name';
        $contributor->save();

        // Pending translations should be cleared
        $reflection = new ReflectionClass($contributor);
        $property = $reflection->getProperty('pending_translations');
        $property->setAccessible(true);

        expect($property->getValue($contributor))->toBe([]);
    });
});

describe('getTranslationModelClass', function (): void {
    it('resolves correct translation model class', function (): void {
        $contributor = Contributor::factory()->create();
        $reflection = new ReflectionClass($contributor);
        $method = $reflection->getMethod('getTranslationModelClass');
        $method->setAccessible(true);

        $translationClass = $method->invoke(null);

        expect($translationClass)->toBe('Modules\\Cms\\Models\\Translations\\ContributorTranslation');
    });
});

describe('forLocale scope', function (): void {
    it('filters models by locale', function (): void {
        $contributor1 = Contributor::factory()->create();
        $contributor2 = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor1->setTranslation($default_locale, ['name' => 'Italian Contributor']);
        $contributor1->setTranslation('en', ['name' => 'English Contributor']);
        $contributor1->save();

        $contributor2->setTranslation('en', ['name' => 'English Contributor 2']);
        $contributor2->save();

        // Filter by English locale (scope method)
        $englishContributors = Contributor::query()->forLocale('en')->get();

        expect($englishContributors)->toHaveCount(2);
        expect($englishContributors->pluck('id')->toArray())->toContain($contributor1->id, $contributor2->id);
    });

    it('removes default locale scope when using forLocale', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Default Contributor']);
        $contributor->setTranslation('en', ['name' => 'English Contributor']);
        $contributor->save();

        // Should be able to query for any locale, not just default
        $enContributors = Contributor::query()->forLocale('en')->get();

        expect($enContributors)->toHaveCount(1);
    });
});

describe('withTranslation scope', function (): void {
    it('eager loads translation without filtering', function (): void {
        $contributor1 = Contributor::factory()->create();
        $contributor2 = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor1->setTranslation($default_locale, ['name' => 'Contributor 1']);
        $contributor1->setTranslation('en', ['name' => 'Contributor 1 EN']);
        $contributor1->save();

        $contributor2->setTranslation($default_locale, ['name' => 'Contributor 2']);
        $contributor2->save();

        // Should load all contributors but with English translation if available
        $contributors = Contributor::query()->withTranslation('en')->get();

        expect($contributors)->toHaveCount(2);
        expect($contributors->first()->getRelationValue('translation'))->not->toBeNull();
    });
});

describe('Integration with different models', function (): void {
    it('works with Content model', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        $content->setTranslation($default_locale, [
            'title' => 'Test Title',
            'slug' => 'test-slug',
        ]);
        $content->save();

        expect($content->title)->toBe('Test Title');
        expect($content->slug)->toBe('test-slug');
    });

    it('works with Category model', function (): void {
        $category = Category::factory()->create();
        $default_locale = config('app.locale');

        $category->setTranslation($default_locale, [
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);
        $category->save();

        expect($category->name)->toBe('Test Category');
        expect($category->slug)->toBe('test-category');
    });

    it('works with Tag model', function (): void {
        $tag = Tag::factory()->create();
        $default_locale = config('app.locale');

        $tag->setTranslation($default_locale, [
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);
        $tag->save();

        expect($tag->name)->toBe('Test Tag');
        expect($tag->slug)->toBe('test-tag');
    });

    it('can handle components translation', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        $content->setTranslation($default_locale, [
            'title' => 'Test Title',
            'slug' => 'test-slug',
            'components' => [
                'body' => 'Test body',
                'excerpt' => 'Test excerpt',
            ],
        ]);
        $content->save();

        expect($content->title)->toBe('Test Title');
        expect($content->body)->toBe('Test body');
        expect($content->excerpt)->toBe('Test excerpt');
    });
});

describe('Fallback behavior', function (): void {
    it('can handle fallback when translation missing', function (): void {
        $content = Content::factory()->create();
        $default_locale = config('app.locale');

        // Set only default translation
        $content->setTranslation($default_locale, [
            'title' => 'Default Title',
            'slug' => 'default-slug',
        ]);
        $content->save();

        // Try to access with different locale (should fallback to default if enabled)
        $original_locale = LocaleContext::get();
        LocaleContext::set('fr');

        // If fallback is enabled, should get default, otherwise null
        $title = $content->title;
        expect($title)->not->toBeNull();

        // Restore original locale
        LocaleContext::set($original_locale);
    });

    it('uses fallback when enabled and translation missing', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Default Name']);
        $contributor->save();

        // Test with fallback enabled
        $originalLocale = LocaleContext::get();
        LocaleContext::set('fr');

        // Should fallback to default when fallback is enabled
        $name = $contributor->getTranslation('fr', true)?->name ?? $contributor->name;

        expect($name)->toBe('Default Name');

        LocaleContext::set($originalLocale);
    });

    it('returns null when fallback disabled and translation missing', function (): void {
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, ['name' => 'Default Name']);
        $contributor->save();

        // Test with fallback disabled
        $originalLocale = LocaleContext::get();
        LocaleContext::set('fr');

        // Should return null when fallback is disabled
        $translation = $contributor->getTranslation('fr', false);
        expect($translation)->toBeNull();

        LocaleContext::set($originalLocale);
    });
});
