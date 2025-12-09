<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cms\Models\Category;
use Modules\Cms\Models\Content;
use Modules\Cms\Models\Tag;
use Modules\Core\Helpers\LocaleContext;

uses(RefreshDatabase::class);

it('can get translatable fields', function (): void {
    $content = Content::factory()->create();
    $fields = $content->getTranslatableFields();

    expect($fields)->toBeArray();
    expect($fields)->toContain('title', 'slug', 'components');
});

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

it('can check if translation exists', function (): void {
    $content = Content::factory()->create();
    $default_locale = config('app.locale');

    expect($content->hasTranslation($default_locale))->toBeTrue();
    expect($content->hasTranslation('fr'))->toBeFalse();
});

it('can get translation for specific locale', function (): void {
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

it('can use inLocale to set translation for specific locale', function (): void {
    $content = Content::factory()->create();

    $content->inLocale('en')->title = 'English Title';
    $content->inLocale('en')->slug = 'english-slug';
    $content->save();

    expect($content->getTranslation('en')->title)->toBe('English Title');
    expect($content->getTranslation('en')->slug)->toBe('english-slug');
});

it('can handle fallback when translation missing', function (): void {
    $content = Content::factory()->create();
    $default_locale = config('app.locale');

    // Set only default translation
    $content->setTranslation($default_locale, [
        'title' => 'Default Title',
        'slug' => 'default-slug',
    ]);
    $content->save();

    // Enable fallback (if supported)
    if (method_exists(LocaleContext::class, 'enableFallback')) {
        LocaleContext::enableFallback();
    }

    // Try to access with different locale (should fallback to default if enabled)
    $original_locale = LocaleContext::get();

    if (method_exists(LocaleContext::class, 'set')) {
        LocaleContext::set('fr');
    }

    // If fallback is enabled, should get default, otherwise null
    $title = $content->title;
    expect($title)->not->toBeNull();

    // Restore original locale
    if (method_exists(LocaleContext::class, 'set')) {
        LocaleContext::set($original_locale);
    }
});

it('can work with Category translations', function (): void {
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

it('can work with Tag translations', function (): void {
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
