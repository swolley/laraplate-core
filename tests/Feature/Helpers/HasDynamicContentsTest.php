<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\CMS\Casts\EntityType;
use Modules\CMS\Enums\CMSTables;
use Modules\CMS\Models\Contributor;
use Modules\CMS\Models\Entity;
use Modules\CMS\Models\Pivot\Presettable;
use Modules\CMS\Models\Preset;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Field;
use Modules\Core\Services\DynamicContentsService;

beforeEach(function (): void {
    // Base tables store shared_components; translated `components` live on *_translations.
    if (! Schema::hasColumn(CMSTables::Contributors->value, 'shared_components')) {
        $this->markTestSkipped('Dynamic contents integration requires full Core runtime.');
    }

    setupCMSEntities([EntityType::Contributors]);
});

/**
 * Create a CMS entity with specific field types and a versioned presettable snapshot.
 *
 * @return array{
 *     entity: Entity,
 *     preset: Preset,
 *     presettable: Presettable,
 *     textField: Field,
 *     arrayField: Field,
 *     objectField: Field,
 *     editorField: Field
 * }
 */
function createTestEntityWithFields(): array
{
    DynamicContentsService::reset();

    $entity = Entity::query()->create([
        'name' => 'test_entity_' . uniqid(),
        'slug' => 'test-entity-' . uniqid(),
        'type' => EntityType::Contributors,
    ]);

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'default_' . uniqid(),
    ]);

    $textField = Field::query()->create([
        'name' => 'text_field',
        'type' => FieldType::Text,
        'options' => new stdClass(),
    ]);
    $textField->is_translatable = true;
    $textField->save();

    $arrayField = Field::query()->create([
        'name' => 'array_field',
        'type' => FieldType::Array,
        'options' => new stdClass(),
    ]);
    $arrayField->is_translatable = true;
    $arrayField->save();

    $objectField = Field::query()->create([
        'name' => 'object_field',
        'type' => FieldType::Object,
        'options' => new stdClass(),
    ]);
    $objectField->is_translatable = true;
    $objectField->save();

    $editorField = Field::query()->create([
        'name' => 'editor_field',
        'type' => FieldType::Editor,
        'options' => new stdClass(),
    ]);
    $editorField->is_translatable = true;
    $editorField->save();

    $preset->fields()->sync([
        $textField->id => ['default' => null, 'is_required' => false, 'order_column' => 0],
        $arrayField->id => ['default' => null, 'is_required' => false, 'order_column' => 1],
        $objectField->id => ['default' => null, 'is_required' => false, 'order_column' => 2],
        $editorField->id => ['default' => null, 'is_required' => false, 'order_column' => 3],
    ]);

    $presettable = $preset->createFieldsVersion();

    return [
        'entity' => $entity,
        'preset' => $preset,
        'presettable' => $presettable,
        'textField' => $textField,
        'arrayField' => $arrayField,
        'objectField' => $objectField,
        'editorField' => $editorField,
    ];
}

/**
 * Contributor bound to the custom entity/presettable under test.
 */
function contributorOnEntity(Entity $entity, Presettable $presettable): Contributor
{
    return Contributor::factory()->create([
        'entity_id' => $entity->id,
        'presettable_id' => $presettable->id,
    ]);
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
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);
        $default_locale = config('app.locale');

        $components = [
            'text_field' => 'Test Text',
            'array_field' => ['item1', 'item2'],
            'object_field' => new stdClass(),
            'editor_field' => ['blocks' => []],
        ];

        $contributor->setTranslation($default_locale, [
            'components' => $components,
        ]);
        $contributor->save();

        // Verify components are saved in translations table, not in contributors table
        $translation = $contributor->getConnection()->table(CMSTables::ContributorsTranslations->value)
            ->where('contributor_id', $contributor->id)
            ->where('locale', $default_locale)
            ->first();

        expect($translation)->not->toBeNull();
        expect(json_decode((string) $translation->components, true))->toBeArray();

        // Verify components are NOT in contributors table
        $contributorRecord = $contributor->getConnection()->table($contributor->getTable())->where('id', $contributor->id)->first();
        expect($contributorRecord)->not->toHaveProperty('components');
    });

    it('can access dynamic content fields transparently with HasTranslatedDynamicContents', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);
        $default_locale = config('app.locale');

        $contributor->setTranslation($default_locale, [
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
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');
        $contributor->setTranslation($default_locale, [
            'components' => [], // Empty components
        ]);
        $contributor->save();

        // array_field should have [] as default, not null
        $components = $contributor->components;
        expect($components['array_field'])->toBeArray();
        expect($components['array_field'])->toBe([]);
    });

    it('ensures OBJECT fields have object default value instead of null', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');
        $contributor->setTranslation($default_locale, [
            'components' => [], // Empty components
        ]);
        $contributor->save();

        // object_field should have stdClass() as default, not null
        $components = $contributor->components;
        expect($components['object_field'])->toBeInstanceOf(stdClass::class);
    });

    it('ensures EDITOR fields have array default value instead of null', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');
        $contributor->setTranslation($default_locale, [
            'components' => [], // Empty components
        ]);
        $contributor->save();

        // editor_field should have ['blocks' => []] as default, not null
        $components = $contributor->components;
        expect($components['editor_field'])->toBeArray();
        expect($components['editor_field'])->toHaveKey('blocks');
        expect($components['editor_field']['blocks'])->toBe([]);
    });
});

describe('Validation', function (): void {
    it('validates ARRAY fields correctly', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        // Should pass validation with array value
        $contributor->setTranslation($default_locale, [
            'components' => [
                'array_field' => ['item1', 'item2'],
            ],
        ]);

        // Model already exists: use update rules (create unique would fail on own name).
        expect(fn () => $contributor->validateWithRules('update'))->not->toThrow(Exception::class);
    });

    it('validates OBJECT fields correctly by converting to JSON string', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        // Should pass validation with object value (converted to JSON string)
        $contributor->setTranslation($default_locale, [
            'components' => [
                'object_field' => new stdClass(),
            ],
        ]);

        expect(fn () => $contributor->validateWithRules('update'))->not->toThrow(Exception::class);
    });

    it('validates EDITOR fields correctly by converting to JSON string', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        // Should pass validation with editor value (converted to JSON string)
        $contributor->setTranslation($default_locale, [
            'components' => [
                'editor_field' => ['blocks' => []],
            ],
        ]);

        expect(fn () => $contributor->validateWithRules('update'))->not->toThrow(Exception::class);
    });

    it('fails validation when ARRAY field is not an array', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        // Set array_field as string instead of array
        $contributor->setTranslation($default_locale, [
            'components' => [
                'array_field' => 'not an array',
            ],
        ]);

        expect(fn () => $contributor->validateWithRules('update'))->toThrow(Illuminate\Validation\ValidationException::class);
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
        expect($contributor->getAttributes())->not->toHaveKey('components');
    });

    it('removes components from attributes after HasDynamicContents adds it', function (): void {
        // Create instance using factory to ensure database is ready
        $contributor = Contributor::factory()->make();

        // Contributor overrides initializeHasDynamicContents to clean translatable fields;
        // call the aliased base initializer to re-add components, then assert cleanup.
        $base_initialize = new ReflectionMethod($contributor, '_internalDynamicContentsInitialize');
        $base_initialize->invoke($contributor);
        expect($contributor->getAttributes())->toHaveKey('components');

        $contributor->initializeHasTranslatedDynamicContents();
        expect($contributor->getAttributes())->not->toHaveKey('components');
    });
});

describe('Integration with HasTranslations', function (): void {
    it('components is a translatable field when using HasTranslatedDynamicContents', function (): void {
        $contributor = Contributor::factory()->create();
        $translatable_fields = $contributor::getTranslatableFields();

        expect($translatable_fields)->toContain('components');
    });

    it('can set components for different locales', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $contributor = contributorOnEntity($entity, $presettable);

        // Use two distinct locales so default app.locale=en does not collide.
        $contributor->setTranslation('it', [
            'components' => [
                'text_field' => 'Testo Italiano',
            ],
        ]);

        $contributor->setTranslation('en', [
            'components' => [
                'text_field' => 'English Text',
            ],
        ]);
        $contributor->save();

        Modules\Core\Helpers\LocaleContext::set('it');
        $contributor->unsetRelation('translation');
        expect($contributor->text_field)->toBe('Testo Italiano');

        $enTranslation = $contributor->getTranslation('en');
        expect($enTranslation->components['text_field'])->toBe('English Text');

        Modules\Core\Helpers\LocaleContext::set(config('app.locale'));
    });
});
