<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Inspector\Entities\Column;
use Modules\Core\Inspector\Entities\ForeignKey;
use Modules\Core\Inspector\Entities\Index;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\DynamicEntity;
use Modules\Core\Services\DynamicEntityService;


beforeEach(function (): void {
    DynamicEntityService::reset();
});

it('tryResolveModel returns null when no model matches', function (): void {
    expect(DynamicEntity::tryResolveModel('nonexistent_entity_' . bin2hex(random_bytes(4))))->toBeNull();
});

it('tryResolveModel returns class string when connection is compatible', function (): void {
    $resolved = DynamicEntity::tryResolveModel('setting', null);

    expect($resolved)->toBe(Modules\Core\Models\Setting::class);
});

it('tryResolveModel returns null when request connection does not match model connection', function (): void {
    $resolved = DynamicEntity::tryResolveModel('settings', 'mysql_nonexistent_for_test');

    expect($resolved)->toBeNull();
});

it('findModel throws when multiple classes match the same entity name', function (): void {
    $method = new ReflectionMethod(DynamicEntity::class, 'findModel');
    $method->setAccessible(true);

    $duplicate_classes = [
        'App\\Models\\SomeThing',
        'Vendor\\Package\\SomeThing',
    ];

    expect(fn () => $method->invoke(null, $duplicate_classes, 'some_thing'))
        ->toThrow(Exception::class);
});

it('findModel returns the only matching class', function (): void {
    $method = new ReflectionMethod(DynamicEntity::class, 'findModel');
    $method->setAccessible(true);

    $resolved = $method->invoke(null, ['App\\Models\\OnlyThing'], 'only_thing');

    expect($resolved)->toBe('App\\Models\\OnlyThing');
});

it('columnTypeToValidationRule maps core types and falls back to string', function (): void {
    $entity = new DynamicEntity();
    $method = new ReflectionMethod(DynamicEntity::class, 'columnTypeToValidationRule');
    $method->setAccessible(true);

    expect($method->invoke($entity, 'integer'))->toBe('integer')
        ->and($method->invoke($entity, 'custom_blob'))->toBe('string');
});

it('getFillable returns early when fillable already populated', function (): void {
    $entity = new DynamicEntity();
    $fillable = new ReflectionProperty(DynamicEntity::class, 'fillable');
    $fillable->setAccessible(true);
    $fillable->setValue($entity, ['existing']);

    expect($entity->getFillable())->toBe(['existing']);
});

it('getFillable derives names from inspected columns excluding autoincrement', function (): void {
    $entity = new DynamicEntity();
    $entity->setTable('derived_fillable_table');
    $entity->setConnection(config('database.default'));

    $columns = collect([
        new Column('id', collect(['autoincrement']), null, 'integer'),
        new Column('title', collect([]), null, 'string'),
        new Column('description', collect(['nullable']), null, 'text'),
    ]);
    $table = new Modules\Core\Inspector\Entities\Table('derived_fillable_table', $columns, collect([]), collect([]), 'main', config('database.default'));

    $inspector = SchemaInspector::getInstance();
    $tables = new ReflectionProperty($inspector, 'tables');
    $tables->setAccessible(true);
    $cache = $tables->getValue($inspector);
    $cache[Modules\Core\Inspector\Inspect::keyName('derived_fillable_table', config('database.default'))] = $table;
    $tables->setValue($inspector, $cache);

    $fillable = $entity->getFillable();

    expect($fillable)->toContain('title')
        ->and($fillable)->toContain('description')
        ->and($fillable)->not->toContain('id');
});

it('getRules merges inspected rules into default operation', function (): void {
    $entity = new DynamicEntity();
    $property = new ReflectionProperty(DynamicEntity::class, 'inspected_rules');
    $property->setAccessible(true);
    $property->setValue($entity, [
        DynamicEntity::DEFAULT_RULE => [
            'extra_col' => ['string'],
        ],
    ]);

    $rules = $entity->getRules();

    expect($rules[DynamicEntity::DEFAULT_RULE]['extra_col'] ?? null)->toContain('string');
});

it('jsonSerialize removes bcrypt like string values', function (): void {
    $entity = new DynamicEntity();
    $entity->setTable('dyn_json');
    $hash = '$2y$10$' . str_repeat('a', 53);
    $entity->setRawAttributes([
        'visible' => 'ok',
        'secret' => $hash,
    ]);

    $payload = $entity->jsonSerialize();

    expect($payload)->toHaveKey('visible', 'ok')
        ->and($payload)->not->toHaveKey('secret');
});

it('inspect hydrates metadata from a real table definition', function (): void {
    config()->set('crud.dynamic_entities', true);

    $table = 'dyn_inspect_' . bin2hex(random_bytes(4));

    Schema::create($table, function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('title');
        $blueprint->unsignedInteger('optional_score')->nullable();
        $blueprint->softDeletes();
    });

    try {
        $entity = DynamicEntityService::getInstance()->resolve($table);

        expect($entity->getFillable())->toContain('title')
            ->and($entity->getDynamicRelations())->toBeArray();
    } finally {
        DynamicEntityService::getInstance()->clearCache($table);
        Schema::dropIfExists($table);
    }
});

it('inspect with relations request resolves reverse relations when tables reference each other', function (): void {
    config()->set('crud.dynamic_entities', true);

    $parent = 'dyn_parent_' . bin2hex(random_bytes(4));
    $child = 'dyn_child_' . bin2hex(random_bytes(4));

    Schema::create($parent, function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('name')->nullable();
    });

    Schema::create($child, function (Blueprint $blueprint) use ($parent): void {
        $blueprint->id();
        $blueprint->foreignId('parent_id')->constrained($parent);
    });

    try {
        $request = Request::create('/test', 'GET', [
            'relations' => [$parent],
        ]);

        DynamicEntityService::getInstance()->resolve($child, request: $request);

        expect(true)->toBeTrue();
    } finally {
        DynamicEntityService::getInstance()->clearCache($child);
        DynamicEntityService::getInstance()->clearCache($parent);
        Schema::dropIfExists($child);
        Schema::dropIfExists($parent);
    }
});

it('verifyTableExistence throws when table is missing', function (): void {
    config()->set('crud.dynamic_entities', true);

    $method = new ReflectionMethod(DynamicEntity::class, 'verifyTableExistence');
    $method->setAccessible(true);

    $entity = new DynamicEntity();
    $entity->setTable('missing_table_' . bin2hex(random_bytes(4)));

    expect(fn () => $method->invoke($entity))->toThrow(UnexpectedValueException::class);
});

it('setPrimaryKeyInfo handles composite primary keys', function (): void {
    $entity = new DynamicEntity();
    $method = new ReflectionMethod(DynamicEntity::class, 'setPrimaryKeyInfo');
    $method->setAccessible(true);

    $index = new Index('pk', collect(['a', 'b']), collect(['primary']));
    $columns = collect([
        new Column('a', collect(['nullable']), null, 'integer'),
        new Column('b', collect(['nullable']), null, 'integer'),
    ]);

    $method->invoke($entity, $index, $columns);

    expect($entity->getKeyName())->toEqual(collect(['a', 'b']))
        ->and($entity->getKeyType())->toBe('string')
        ->and($entity->getIncrementing())->toBeFalse();
});

it('setColumnInfo adds unique rule for single column unique indexes', function (): void {
    $entity = new DynamicEntity();
    $entity->setTable('dyn_rules');

    $method = new ReflectionMethod(DynamicEntity::class, 'setColumnInfo');
    $method->setAccessible(true);

    $unique_index = new Index('dyn_rules_slug_unique', collect(['slug']), collect(['unique']));

    $column = new Column('slug', collect(['nullable']), null, 'string');

    $method->invoke($entity, $column, collect([]), collect([$unique_index]));

    $rules = $entity->getRules();

    expect($rules[DynamicEntity::DEFAULT_RULE]['slug'] ?? [])
        ->toContain('string');

    $slug_rules = $rules[DynamicEntity::DEFAULT_RULE]['slug'];
    $has_unique = collect($slug_rules)->contains(fn ($r): bool => is_object($r) && $r instanceof Illuminate\Validation\Rules\Unique);

    expect($has_unique)->toBeTrue();
});

it('setTableConnectionInfo sets connection only when value is meaningful', function (): void {
    $entity = new DynamicEntity();
    $method = new ReflectionMethod(DynamicEntity::class, 'setTableConnectionInfo');
    $method->setAccessible(true);

    $method->invoke($entity, 'alpha_table', 'sqlite');
    expect($entity->getConnectionName())->toBe('sqlite');

    $method->invoke($entity, 'beta_table', '');
    expect($entity->getConnectionName())->toBe('sqlite');
});

it('setColumnInfo adds max length and soft-delete unique callback behavior', function (): void {
    $entity = new DynamicEntity();
    $entity->setTable('dyn_soft_delete_rules');

    $force_deleting = new ReflectionProperty($entity, 'forceDeleting');
    $force_deleting->setAccessible(true);
    $force_deleting->setValue($entity, true);

    $method = new ReflectionMethod(DynamicEntity::class, 'setColumnInfo');
    $method->setAccessible(true);

    $deleted_column = new Column('deleted_at', collect(['nullable']), null, 'datetime');
    $indexes = collect([new Index('deleted_at_unique', collect(['deleted_at']), collect(['unique']))]);
    $method->invoke($entity, $deleted_column, collect([]), $indexes);

    $rules = $entity->getRules();
    $deleted_rules = $rules[DynamicEntity::DEFAULT_RULE]['deleted_at'] ?? [];

    expect($deleted_rules)->toContain('date');

    $unique_rule = collect($deleted_rules)->first(fn (mixed $r): bool => $r instanceof Illuminate\Validation\Rules\Unique);
    expect($unique_rule)->toBeInstanceOf(Illuminate\Validation\Rules\Unique::class);

    $query = Mockery::mock(Illuminate\Database\Query\Builder::class);
    $query->shouldReceive('whereNull')->once()->with('deleted_at')->andReturnSelf();

    foreach ($unique_rule->queryCallbacks() as $callback) {
        $callback($query);
    }

    expect($force_deleting->getValue($entity))->toBeFalse();
});

it('setReverseRelationsInfo returns immediately when relations are missing', function (): void {
    $entity = new DynamicEntity();
    $method = new ReflectionMethod(DynamicEntity::class, 'setReverseRelationsInfo');
    $method->setAccessible(true);

    $request = Request::create('/test', 'GET');
    $method->invoke($entity, $request);

    expect($entity->getDynamicRelations())->toBe([]);
});

it('setReverseRelationInfo adds belongsToMany relation for matching reverse entry', function (): void {
    $entity = new DynamicEntity();
    $method = new ReflectionMethod(DynamicEntity::class, 'setReverseRelationInfo');
    $method->setAccessible(true);

    $target = 'dyn_target_' . bin2hex(random_bytes(4));
    $source = 'dyn_source_' . bin2hex(random_bytes(4));

    try {
        config()->set('crud.dynamic_entities', true);
        $entity->setTable($target);
        $entity->setConnection(config('database.default'));

        $relation_data = new ForeignKey(
            'fk_demo',
            collect(['target_id']),
            'main',
            $target,
            collect(['id']),
            'main',
            config('database.default'),
        );
        $source_entity = new DynamicEntity();
        $source_entity->setTable($source);
        $source_entity->setConnection(config('database.default'));
        $dynamic_relations = new ReflectionProperty(DynamicEntity::class, 'dynamic_relations');
        $dynamic_relations->setAccessible(true);
        $dynamic_relations->setValue($source_entity, [$target => $relation_data]);

        $service = DynamicEntityService::getInstance();
        $resolved_cache = new ReflectionProperty($service, 'resolved_cache');
        $resolved_cache->setAccessible(true);
        $cache = $resolved_cache->getValue($service);
        $cache_key = sprintf('dynamic_entities.%s.%s', config('database.default'), $source);
        $cache[$cache_key] = $source_entity;
        $resolved_cache->setValue($service, $cache);

        $method->invoke($entity, $source);
        $relations = $entity->getDynamicRelations();

        expect($relations)->toHaveKey($source)
            ->and($relations[$source]['type'])->toBe('belongsToMany')
            ->and($relations[$source]['foreignKey'])->toBeInstanceOf(ForeignKey::class);
    } finally {
        DynamicEntityService::getInstance()->clearCache($source);
        DynamicEntityService::getInstance()->clearCache($target);
    }
});
