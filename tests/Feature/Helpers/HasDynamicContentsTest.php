<?php

declare(strict_types=1);

use App\Casts\EntityType;
use App\Models\Author;
use App\Models\Entity;
use App\Models\Pivot\Presettable;
use App\Models\Preset;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Field;
use Modules\Core\Services\DynamicContentsService;

beforeEach(function (): void {
    // Base table stores shared_components; translated `components` live on the translations table.
    Author::createTables();
});

afterEach(function (): void {
    Author::dropTables();
});

/**
 * Create an App entity with specific field types and a versioned presettable snapshot.
 *
 * @return array{
 *     entity: Entity,
 *     preset: Preset,
 *     presettable: Presettable,
 *     textField: Field,
 *     arrayField: Field,
 *     objectField: Field,
 *     editorField: Field,
 *     numberField: Field
 * }
 */
function createTestEntityWithFields(): array
{
    DynamicContentsService::reset();

    $entity = Entity::query()->create([
        'name' => 'test_entity_' . uniqid(),
        'slug' => 'test-entity-' . uniqid(),
        'type' => EntityType::Authors,
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

    $numberField = Field::query()->create([
        'name' => 'number_field',
        'type' => FieldType::Number,
        'options' => new stdClass(),
    ]);
    $numberField->is_translatable = true;
    $numberField->save();

    $preset->fields()->sync([
        $textField->id => ['default' => null, 'is_required' => false, 'order_column' => 0],
        $arrayField->id => ['default' => null, 'is_required' => false, 'order_column' => 1],
        $objectField->id => ['default' => null, 'is_required' => false, 'order_column' => 2],
        $editorField->id => ['default' => null, 'is_required' => false, 'order_column' => 3],
        $numberField->id => ['default' => null, 'is_required' => false, 'order_column' => 4],
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
        'numberField' => $numberField,
    ];
}

/**
 * Author bound to the custom entity/presettable under test, with an empty default-locale
 * translation so it is visible through the locale global scope.
 */
function authorOnEntity(Entity $entity, Presettable $presettable): Author
{
    $author = Author::query()->create([
        'entity_id' => $entity->id,
        'presettable_id' => $presettable->id,
        'name' => 'author_' . uniqid(),
    ]);
    $author->setTranslation((string) config('app.locale'), ['components' => []]);

    return $author;
}

describe('HasTranslatedDynamicContents', function (): void {
    it('removes components from fillable when using HasTranslatedDynamicContents', function (): void {
        $author = new Author();
        $author->initializeHasDynamicContents();
        $author->initializeHasTranslations();
        $author->initializeHasTranslatedDynamicContents();

        expect($author->getFillable())->not->toContain('components');
        expect($author->attributes)->not->toHaveKey('components');
    });

    it('saves components in translations table when using HasTranslatedDynamicContents', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);
        $default_locale = config('app.locale');

        $components = [
            'text_field' => 'Test Text',
            'array_field' => ['item1', 'item2'],
            'object_field' => new stdClass(),
            'editor_field' => ['blocks' => []],
        ];

        $author->setTranslation($default_locale, [
            'components' => $components,
        ]);
        $author->save();

        // Verify components are saved in translations table, not in authors table
        $translation = $author->getConnection()->table(Author::TRANSLATIONS_TABLE)
            ->where('author_id', $author->id)
            ->where('locale', $default_locale)
            ->first();

        expect($translation)->not->toBeNull();
        expect(json_decode((string) $translation->components, true))->toBeArray();

        // Verify components are NOT in authors table
        $author_record = $author->getConnection()->table($author->getTable())->where('id', $author->id)->first();
        expect($author_record)->not->toHaveProperty('components');
    });

    it('can access dynamic content fields transparently with HasTranslatedDynamicContents', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);
        $default_locale = config('app.locale');

        $author->setTranslation($default_locale, [
            'components' => [
                'text_field' => 'Test Text',
                'array_field' => ['item1', 'item2'],
            ],
        ]);
        $author->save();

        // Access as property
        expect($author->text_field)->toBe('Test Text');
        expect($author->array_field)->toBe(['item1', 'item2']);
    });
});

describe('mergeComponentsValues', function (): void {
    it('ensures ARRAY fields have array default value instead of null', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');
        $author->setTranslation($default_locale, [
            'components' => [], // Empty components
        ]);
        $author->save();

        // array_field should have [] as default, not null
        $components = $author->components;
        expect($components['array_field'])->toBeArray();
        expect($components['array_field'])->toBe([]);
    });

    it('ensures OBJECT fields have object default value instead of null', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');
        $author->setTranslation($default_locale, [
            'components' => [], // Empty components
        ]);
        $author->save();

        // object_field should have stdClass() as default, not null
        $components = $author->components;
        expect($components['object_field'])->toBeInstanceOf(stdClass::class);
    });

    it('ensures EDITOR fields have array default value instead of null', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');
        $author->setTranslation($default_locale, [
            'components' => [], // Empty components
        ]);
        $author->save();

        // editor_field should have ['blocks' => []] as default, not null
        $components = $author->components;
        expect($components['editor_field'])->toBeArray();
        expect($components['editor_field'])->toHaveKey('blocks');
        expect($components['editor_field']['blocks'])->toBe([]);
    });
});

describe('Validation', function (): void {
    it('validates ARRAY fields correctly', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        // Should pass validation with array value
        $author->setTranslation($default_locale, [
            'components' => [
                'array_field' => ['item1', 'item2'],
            ],
        ]);

        // Model already exists: use update rules (create unique would fail on own name).
        expect(fn () => $author->validateWithRules('update'))->not->toThrow(Exception::class);
    });

    it('validates OBJECT fields correctly by converting to JSON string', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        // Should pass validation with object value (converted to JSON string)
        $author->setTranslation($default_locale, [
            'components' => [
                'object_field' => new stdClass(),
            ],
        ]);

        expect(fn () => $author->validateWithRules('update'))->not->toThrow(Exception::class);
    });

    it('validates EDITOR fields correctly by converting to JSON string', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        // Should pass validation with editor value (converted to JSON string)
        $author->setTranslation($default_locale, [
            'components' => [
                'editor_field' => ['blocks' => []],
            ],
        ]);

        expect(fn () => $author->validateWithRules('update'))->not->toThrow(Exception::class);
    });

    it('validates NUMBER fields correctly with a numeric value', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        $author->setTranslation($default_locale, [
            'components' => [
                'number_field' => 42,
            ],
        ]);

        expect(fn () => $author->validateWithRules('update'))->not->toThrow(Exception::class);
    });

    it('fails validation when NUMBER field is not numeric', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        $author->setTranslation($default_locale, [
            'components' => [
                'number_field' => 'not a number',
            ],
        ]);

        expect(fn () => $author->validateWithRules('update'))->toThrow(Illuminate\Validation\ValidationException::class);
    });

    it('fails validation when ARRAY field is not an array', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        $default_locale = config('app.locale');

        // Set array_field as string instead of array
        $author->setTranslation($default_locale, [
            'components' => [
                'array_field' => 'not an array',
            ],
        ]);

        expect(fn () => $author->validateWithRules('update'))->toThrow(Illuminate\Validation\ValidationException::class);
    });
});

describe('initializeHasTranslatedDynamicContents', function (): void {
    it('removes components from fillable when called', function (): void {
        $author = new Author();

        // initializeHasDynamicContents is called automatically and adds components
        // initializeHasTranslatedDynamicContents should remove it
        // Note: In Laravel, initialize methods are called automatically, but the order
        // may vary. We verify that initializeHasTranslatedDynamicContents works correctly
        $author->initializeHasTranslatedDynamicContents();

        $fillable = $author->getFillable();

        // components should NOT be in fillable after initializeHasTranslatedDynamicContents
        expect($fillable)->not->toContain('components');

        // Also verify it's not in attributes
        expect($author->getAttributes())->not->toHaveKey('components');
    });

    it('removes components from attributes after HasDynamicContents adds it', function (): void {
        $author = new Author();

        // HasTranslatedDynamicContents overrides initializeHasDynamicContents to clean translatable
        // fields; call the aliased base initializer to re-add components, then assert cleanup.
        $base_initialize = new ReflectionMethod($author, '_internalDynamicContentsInitialize');
        $base_initialize->invoke($author);
        expect($author->getAttributes())->toHaveKey('components');

        $author->initializeHasTranslatedDynamicContents();
        expect($author->getAttributes())->not->toHaveKey('components');
    });
});

describe('Integration with HasTranslations', function (): void {
    it('components is a translatable field when using HasTranslatedDynamicContents', function (): void {
        $author = new Author();
        $translatable_fields = $author::getTranslatableFields();

        expect($translatable_fields)->toContain('components');
    });

    it('can set components for different locales', function (): void {
        ['entity' => $entity, 'presettable' => $presettable] = createTestEntityWithFields();
        $author = authorOnEntity($entity, $presettable);

        // Use two distinct locales so default app.locale=en does not collide.
        $author->setTranslation('it', [
            'components' => [
                'text_field' => 'Testo Italiano',
            ],
        ]);

        $author->setTranslation('en', [
            'components' => [
                'text_field' => 'English Text',
            ],
        ]);
        $author->save();

        Modules\Core\Helpers\LocaleContext::set('it');
        $author->unsetRelation('translation');
        expect($author->text_field)->toBe('Testo Italiano');

        $enTranslation = $author->getTranslation('en');
        expect($enTranslation->components['text_field'])->toBe('English Text');

        Modules\Core\Helpers\LocaleContext::set(config('app.locale'));
    });
});
