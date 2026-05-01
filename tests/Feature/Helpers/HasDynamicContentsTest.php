<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Contributor;
use Modules\Core\Models\Entity;
use Modules\Core\Models\Field;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;
use Modules\Core\Tests\Stubs\Casts\EntityTypeStub;

beforeEach(function (): void {
    if (! Schema::hasColumns('contributors', ['components', 'shared_components'])) {
        $this->markTestSkipped('Dynamic contents integration requires full Core runtime.');
    }

    setupCmsEntities();
});

/**
 * Create a test entity with specific field types and a properly linked preset.
 */
function createTestEntityWithFields(): array
{
    $entity = Entity::query()->create([
        'name' => 'test_entity_' . uniqid(),
        'type' => EntityTypeStub::VALUE1,
    ]);

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'default',
    ]);

    Presettable::query()->create([
        'entity_id' => $entity->id,
        'preset_id' => $preset->id,
    ]);

    $textField = Field::query()->create([
        'name' => 'text_field_' . uniqid(),
        'type' => FieldType::TEXT,
        'options' => new stdClass(),
    ]);

    $arrayField = Field::query()->create([
        'name' => 'array_field_' . uniqid(),
        'type' => FieldType::ARRAY,
        'options' => new stdClass(),
    ]);

    $objectField = Field::query()->create([
        'name' => 'object_field_' . uniqid(),
        'type' => FieldType::OBJECT,
        'options' => new stdClass(),
    ]);

    $editorField = Field::query()->create([
        'name' => 'editor_field_' . uniqid(),
        'type' => FieldType::EDITOR,
        'options' => new stdClass(),
    ]);

    $preset->fields()->attach([
        $textField->id => ['default' => null, 'is_required' => false],
        $arrayField->id => ['default' => null, 'is_required' => false],
        $objectField->id => ['default' => null, 'is_required' => false],
        $editorField->id => ['default' => null, 'is_required' => false],
    ]);

    return [
        'entity' => $entity,
        'preset' => $preset,
        'textField' => $textField,
        'arrayField' => $arrayField,
        'objectField' => $objectField,
        'editorField' => $editorField,
    ];
}

describe('HasTranslatedDynamicContents', function (): void {
    it('removes components from fillable when using HasTranslatedDynamicContents', function (): void {
        // Create instance using factory to ensure database is ready
        $contributor = Contributor::factory()->make();
        $contributor->initializeHasDynamicContents();
        $contributor->initializeHasTranslations();
        $contributor->initializeHasTranslatedDynamicContents();

        expect($contributor->getFillable())->not->toContain('components');
        expect($contributor->attributes)->not->toHaveKey('components');
    });

    it('saves components in translations table when using HasTranslatedDynamicContents', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->entity_id = $entity->id;
        $contributor->save();

        $components = [
            'text_field' => 'Test Text',
            'array_field' => ['item1', 'item2'],
            'object_field' => new stdClass(),
            'editor_field' => ['blocks' => []],
        ];

        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => $components,
        ]);
        $contributor->save();

        // Verify components are saved in translations table, not in contributors table
        $translation = DB::table('contributors_translations')
            ->where('contributor_id', $contributor->id)
            ->where('locale', $default_locale)
            ->first();

        expect($translation)->not->toBeNull();
        expect(json_decode((string) $translation->components, true))->toBeArray();

        // Verify components are NOT in contributors table
        $contributorRecord = DB::table('contributors')->where('id', $contributor->id)->first();
        expect($contributorRecord)->not->toHaveProperty('components');
    });

    it('can access dynamic content fields transparently with HasTranslatedDynamicContents', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $default_locale = config('app.locale');

        $contributor->entity_id = $entity->id;
        $contributor->save();

        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => [
                'text_field' => 'Test Text',
                'array_field' => ['item1', 'item2'],
            ],
        ]);
        $contributor->save();

        // Access as property
        expect($contributor->text_field)->toBe('Test Text');
        expect($contributor->array_field)->toBe(['item1', 'item2']);
    });
});

describe('mergeComponentsValues', function (): void {
    it('ensures ARRAY fields have array default value instead of null', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $contributor->entity_id = $entity->id;
        $contributor->save();

        $default_locale = config('app.locale');
        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => [], // Empty components
        ]);
        $contributor->save();

        // array_field should have [] as default, not null
        $components = $contributor->getComponentsAttribute();
        expect($components['array_field'])->toBeArray();
        expect($components['array_field'])->toBe([]);
    });

    it('ensures OBJECT fields have object default value instead of null', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $contributor->entity_id = $entity->id;
        $contributor->save();

        $default_locale = config('app.locale');
        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => [], // Empty components
        ]);
        $contributor->save();

        // object_field should have stdClass() as default, not null
        $components = $contributor->getComponentsAttribute();
        expect($components['object_field'])->toBeInstanceOf(stdClass::class);
    });

    it('ensures EDITOR fields have array default value instead of null', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $contributor->entity_id = $entity->id;
        $contributor->save();

        $default_locale = config('app.locale');
        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => [], // Empty components
        ]);
        $contributor->save();

        // editor_field should have ['blocks' => []] as default, not null
        $components = $contributor->getComponentsAttribute();
        expect($components['editor_field'])->toBeArray();
        expect($components['editor_field'])->toHaveKey('blocks');
        expect($components['editor_field']['blocks'])->toBe([]);
    });
});

describe('Validation', function (): void {
    it('validates ARRAY fields correctly', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $contributor->entity_id = $entity->id;
        $contributor->save();

        $default_locale = config('app.locale');

        // Should pass validation with array value
        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => [
                'array_field' => ['item1', 'item2'],
            ],
        ]);

        expect(fn () => $contributor->validateWithRules('create'))->not->toThrow(Exception::class);
    });

    it('validates OBJECT fields correctly by converting to JSON string', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $contributor->entity_id = $entity->id;
        $contributor->save();

        $default_locale = config('app.locale');

        // Should pass validation with object value (converted to JSON string)
        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => [
                'object_field' => new stdClass(),
            ],
        ]);

        expect(fn () => $contributor->validateWithRules('create'))->not->toThrow(Exception::class);
    });

    it('validates EDITOR fields correctly by converting to JSON string', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $contributor->entity_id = $entity->id;
        $contributor->save();

        $default_locale = config('app.locale');

        // Should pass validation with editor value (converted to JSON string)
        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => [
                'editor_field' => ['blocks' => []],
            ],
        ]);

        expect(fn () => $contributor->validateWithRules('create'))->not->toThrow(Exception::class);
    });

    it('fails validation when ARRAY field is not an array', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $contributor->entity_id = $entity->id;
        $contributor->save();

        $default_locale = config('app.locale');

        // Set array_field as string instead of array
        $contributor->setTranslation($default_locale, [
            'name' => 'Test Contributor',
            'components' => [
                'array_field' => 'not an array',
            ],
        ]);

        expect(fn () => $contributor->validateWithRules('create'))->toThrow(Illuminate\Validation\ValidationException::class);
    });
});

describe('initializeHasTranslatedDynamicContents', function (): void {
    it('removes components from fillable when called', function (): void {
        // Create instance using factory to ensure database is ready
        $contributor = Contributor::factory()->make();

        // initializeHasDynamicContents is called automatically and adds components
        // initializeHasTranslatedDynamicContents should remove it
        // Note: In Laravel, initialize methods are called automatically, but the order
        // may vary. We verify that initializeHasTranslatedDynamicContents works correctly
        $contributor->initializeHasTranslatedDynamicContents();

        $fillable = $contributor->getFillable();

        // components should NOT be in fillable after initializeHasTranslatedDynamicContents
        expect($fillable)->not->toContain('components');

        // Also verify it's not in attributes
        $reflection = new ReflectionClass($contributor);
        $attributesProperty = $reflection->getProperty('attributes');
        $attributes = $attributesProperty->getValue($contributor);

        expect($attributes)->not->toHaveKey('components');
    });

    it('removes components from attributes after HasDynamicContents adds it', function (): void {
        // Create instance using factory to ensure database is ready
        $contributor = Contributor::factory()->make();

        // Simulate what happens during initialization
        $contributor->initializeHasDynamicContents();
        expect($contributor->attributes)->toHaveKey('components');

        // Now initializeHasTranslatedDynamicContents should remove it
        $contributor->initializeHasTranslatedDynamicContents();
        expect($contributor->attributes)->not->toHaveKey('components');
    });
});

describe('Integration with HasTranslations', function (): void {
    it('components is a translatable field when using HasTranslatedDynamicContents', function (): void {
        $contributor = Contributor::factory()->create();
        $translatable_fields = $contributor::getTranslatableFields();

        expect($translatable_fields)->toContain('components');
    });

    it('can set components for different locales', function (): void {
        ['entity' => $entity] = createTestEntityWithFields();
        $contributor = Contributor::factory()->create();
        $contributor->entity_id = $entity->id;
        $contributor->save();

        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, [
            'name' => 'Italian Contributor',
            'components' => [
                'text_field' => 'Testo Italiano',
            ],
        ]);

        $contributor->setTranslation('en', [
            'name' => 'English Contributor',
            'components' => [
                'text_field' => 'English Text',
            ],
        ]);
        $contributor->save();

        // Access with default locale
        expect($contributor->text_field)->toBe('Testo Italiano');

        // Access with English locale
        $enTranslation = $contributor->getTranslation('en');
        expect($enTranslation->components['text_field'])->toBe('English Text');
    });
});
