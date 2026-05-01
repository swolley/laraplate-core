<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\AsciiStringType;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\SimpleArrayType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\TimeType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use MatanYadaev\EloquentSpatial\Doctrine\GeometryType;
use Modules\Core\Inspector\Entities\Column;
use Modules\Core\Inspector\Entities\ForeignKey;
use Modules\Core\Inspector\Entities\Index;
use Modules\Core\Inspector\Inspect;
use Modules\Core\Inspector\Types\DoctrineTypeEnum;


it('column entity computes unsigned and length metadata', function (): void {
    $unsigned = new Column('qty', collect([]), null, 'unsignedInteger');
    $with_length = new Column('name', collect([]), null, 'string(120)');

    expect($unsigned->isUnsigned())->toBeFalse()
        ->and($with_length->getLength())->toBe(0);
});

it('foreign key resolves foreign connection and composite flag', function (): void {
    config()->set('database.connections.testing_fk', [
        'driver' => 'sqlite',
        'database' => 'foreign_schema_db',
        'prefix' => '',
    ]);

    $fk = new ForeignKey(
        'fk_posts_user',
        collect(['user_id', 'tenant_id']),
        'foreign_schema_db',
        'users',
        collect(['id', 'tenant_id']),
        'local_schema',
        'sqlite',
    );

    expect($fk->foreignConnection)->toBe('testing_fk')
        ->and($fk->isComposite())->toBeTrue();
});

it('foreign key keeps local connection for same schema and maps object column names', function (): void {
    $fk = new ForeignKey(
        'fk_local',
        collect([(object) ['name' => 'user_id']]),
        'same_schema',
        'users',
        collect([(object) ['name' => 'id']]),
        'same_schema',
        'sqlite',
    );

    expect($fk->foreignConnection)->toBe('sqlite')
        ->and($fk->localColumnNames()->all())->toBe(['user_id'])
        ->and($fk->foreignColumnNames()->all())->toBe(['id'])
        ->and($fk->isComposite())->toBeFalse();
});

it('index entity reports composite and composite primary states', function (): void {
    $composite_primary = new Index('pk_multi', collect(['id', 'tenant_id']), collect(['primary']));

    expect($composite_primary->isComposite())->toBeTrue()
        ->and($composite_primary->isCompositePrimaryKey())->toBeTrue();
});

it('inspect reads table schema and retrieves columns indexes and foreign keys', function (): void {
    $table = 'inspect_demo_' . bin2hex(random_bytes(4));
    $parent = 'inspect_parent_' . bin2hex(random_bytes(4));

    Schema::create($parent, function (Blueprint $blueprint): void {
        $blueprint->id();
    });

    Schema::create($table, function (Blueprint $blueprint) use ($parent): void {
        $blueprint->id();
        $blueprint->string('name');
        $blueprint->foreignId('parent_id')->constrained($parent);
        $blueprint->unique('name');
    });

    try {
        $inspected = Inspect::table($table, config('database.default'));
        $columns = Inspect::columns($table, config('database.default'));
        $indexes = Inspect::indexes($table, config('database.default'));
        $fks = Inspect::foreignKeys($table, config('database.default'));

        expect($inspected)->not->toBeNull()
            ->and($columns->count())->toBeGreaterThan(0)
            ->and($indexes->count())->toBeGreaterThan(0)
            ->and($fks->count())->toBeGreaterThan(0);

        $column = Inspect::column('name', $table, config('database.default'));
        $index = Inspect::index($indexes->first()->name, $table, config('database.default'));
        $foreign = Inspect::foreignKey($fks->first()->name, $table, config('database.default'));

        expect($column)->not->toBeNull()
            ->and($index)->not->toBeNull()
            ->and($foreign)->not->toBeNull();

        // call again to hit cached retrieval path
        $cached = Inspect::table($table, config('database.default'));
        expect($cached)->not->toBeNull();
    } finally {
        Schema::dropIfExists($table);
        Schema::dropIfExists($parent);
    }
});

it('inspect returns null/empty collections for unknown table', function (): void {
    $missing = 'missing_' . bin2hex(random_bytes(4));

    expect(Inspect::table($missing))->toBeNull()
        ->and(Inspect::columns($missing)->all())->toBe([])
        ->and(Inspect::indexes($missing)->all())->toBe([])
        ->and(Inspect::foreignKeys($missing)->all())->toBe([])
        ->and(Inspect::column('id', $missing))->toBeNull()
        ->and(Inspect::index('idx', $missing))->toBeNull()
        ->and(Inspect::foreignKey('fk', $missing))->toBeNull();
});

it('inspect caches using no-tags cache path', function (): void {
    $table = 'inspect_cache_' . bin2hex(random_bytes(4));
    Schema::create($table, function (Blueprint $blueprint): void {
        $blueprint->id();
    });

    Cache::shouldReceive('store')->atLeast()->once()->andReturn(new class
    {
        public function supportsTags(): bool
        {
            return false;
        }
    });
    Cache::shouldReceive('get')->atLeast()->once()->andReturnNull();
    Cache::shouldReceive('forever')->atLeast()->once()->andReturnTrue();

    try {
        expect(Inspect::table($table, config('database.default')))->not->toBeNull();
    } finally {
        Schema::dropIfExists($table);
    }
});

it('inspect caches using tags when taggable store is available', function (): void {
    $table = 'inspect_cache_tags_' . bin2hex(random_bytes(4));
    Schema::create($table, function (Blueprint $blueprint): void {
        $blueprint->id();
    });

    $tagged_cache = Mockery::mock();
    $tagged_cache->shouldReceive('get')->atLeast()->once()->andReturnNull();
    $tagged_cache->shouldReceive('forever')->atLeast()->once()->andReturnTrue();

    Cache::shouldReceive('store')->atLeast()->once()->andReturn(new class
    {
        public function supportsTags(): bool
        {
            return true;
        }

        /**
         * @param  array<int, string>  $tags
         * @return array<int, string>
         */
        public function getCacheTags(array $tags): array
        {
            return $tags;
        }
    });
    Cache::shouldReceive('getCacheTags')->atLeast()->once()->andReturnUsing(static fn (array $tags): array => $tags);
    Cache::shouldReceive('tags')->atLeast()->once()->andReturn($tagged_cache);

    try {
        expect(Inspect::table($table, config('database.default')))->not->toBeNull();
    } finally {
        Schema::dropIfExists($table);
    }
});

it('parseForeignKeys builds fallback names and keeps composite metadata', function (): void {
    config()->set('database.connections.inspector_fk_external', [
        'driver' => 'sqlite',
        'database' => 'external_schema',
        'prefix' => '',
    ]);

    $method = new ReflectionMethod(Inspect::class, 'parseForeignKeys');
    $method->setAccessible(true);

    /** @var Illuminate\Support\Collection<int, ForeignKey> $fks */
    $fks = $method->invoke(
        null,
        [[
            'columns' => ['user_id', 'tenant_id'],
            'foreign_schema' => 'external_schema',
            'foreign_table' => 'users',
            'foreign_columns' => ['id', 'tenant_id'],
            'on_update' => 'cascade',
            'on_delete' => 'restrict',
        ]],
        'local_schema',
        'sqlite',
    );

    $first = $fks->first();
    expect($first)->toBeInstanceOf(ForeignKey::class)
        ->and($first->name)->toBe('users_user_id_tenant_id')
        ->and($first->isComposite())->toBeTrue()
        ->and($first->foreignConnection)->toBe('inspector_fk_external')
        ->and($first->onUpdate)->toBe('cascade')
        ->and($first->onDelete)->toBe('restrict');
});

it('doctrine type enum maps from string and doctrine type instances', function (): void {
    expect(DoctrineTypeEnum::fromString('integer'))->toBe(DoctrineTypeEnum::INTEGER)
        ->and(DoctrineTypeEnum::fromString('something_else'))->toBe(DoctrineTypeEnum::UNKNOWN)
        ->and(DoctrineTypeEnum::fromDoctrine(new BigIntType()))->toBe(DoctrineTypeEnum::BIGINT)
        ->and(DoctrineTypeEnum::fromDoctrine(new BinaryType()))->toBe(DoctrineTypeEnum::BINARY)
        ->and(DoctrineTypeEnum::fromDoctrine(new BlobType()))->toBe(DoctrineTypeEnum::BLOB)
        ->and(DoctrineTypeEnum::fromDoctrine(new BooleanType()))->toBe(DoctrineTypeEnum::BOOLEAN)
        ->and(DoctrineTypeEnum::fromDoctrine(new DateType()))->toBe(DoctrineTypeEnum::DATE)
        ->and(DoctrineTypeEnum::fromDoctrine(new IntegerType()))->toBe(DoctrineTypeEnum::INTEGER)
        ->and(DoctrineTypeEnum::fromDoctrine(new DateIntervalType()))->toBe(DoctrineTypeEnum::DATEINTERVAL)
        ->and(DoctrineTypeEnum::fromDoctrine(new DateTimeType()))->toBe(DoctrineTypeEnum::DATETIME)
        ->and(DoctrineTypeEnum::fromDoctrine(new DateTimeImmutableType()))->toBe(DoctrineTypeEnum::DATETIME_IMMUTABLE)
        ->and(DoctrineTypeEnum::fromDoctrine(new DateTimeTzType()))->toBe(DoctrineTypeEnum::DATETIMETZ)
        ->and(DoctrineTypeEnum::fromDoctrine(new DateTimeTzImmutableType()))->toBe(DoctrineTypeEnum::DATETIMETZ_IMMUTABLE)
        ->and(DoctrineTypeEnum::fromDoctrine(new DecimalType()))->toBe(DoctrineTypeEnum::DECIMAL)
        ->and(DoctrineTypeEnum::fromDoctrine(new AsciiStringType()))->toBe(DoctrineTypeEnum::ASCII_STRING)
        ->and(DoctrineTypeEnum::fromDoctrine(new DateImmutableType()))->toBe(DoctrineTypeEnum::DATE_IMMUTABLE)
        ->and(DoctrineTypeEnum::fromDoctrine(new FloatType()))->toBe(DoctrineTypeEnum::FLOAT)
        ->and(DoctrineTypeEnum::fromDoctrine(new GuidType()))->toBe(DoctrineTypeEnum::GUID)
        ->and(DoctrineTypeEnum::fromDoctrine(new JsonType()))->toBe(DoctrineTypeEnum::JSON)
        ->and(DoctrineTypeEnum::fromDoctrine(new SimpleArrayType()))->toBe(DoctrineTypeEnum::SIMPLE_ARRAY)
        ->and(DoctrineTypeEnum::fromDoctrine(new SmallIntType()))->toBe(DoctrineTypeEnum::SMALLINT)
        ->and(DoctrineTypeEnum::fromDoctrine(new StringType()))->toBe(DoctrineTypeEnum::STRING)
        ->and(DoctrineTypeEnum::fromDoctrine(new TextType()))->toBe(DoctrineTypeEnum::TEXT)
        ->and(DoctrineTypeEnum::fromDoctrine(new TimeType()))->toBe(DoctrineTypeEnum::TIME)
        ->and(DoctrineTypeEnum::fromDoctrine(new TimeImmutableType()))->toBe(DoctrineTypeEnum::TIME_IMMUTABLE)
        ->and(DoctrineTypeEnum::fromDoctrine(new GeometryType()))->toBe(DoctrineTypeEnum::GEOMETRY)
        ->and(DoctrineTypeEnum::fromDoctrine(DoctrineTypeEnum::TEXT))->toBe(DoctrineTypeEnum::TEXT);
});
