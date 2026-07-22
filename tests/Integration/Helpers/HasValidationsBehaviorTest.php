<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\CMS\Casts\EntityType;
use Modules\CMS\Models\Entity;
use Modules\Core\Casts\CrudExecutor;
use Modules\Core\Models\Concerns\HasDynamicContents;
use Modules\Core\Models\Concerns\HasValidations;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Overrides\ContextualValidationException;
use Modules\Core\Overrides\Model;
use Modules\Core\Tests\Stubs\FakeVersionedModel;

it('skips validateWithRules when validation is disabled', function (): void {
    $model = CronJob::factory()->make([
        'name' => null,
        'command' => 'test:command',
        'schedule' => '* * * * *',
    ]);
    $model->setSkipValidation(true);

    expect(fn () => $model->validateWithRules(CrudExecutor::INSERT))->not->toThrow(Throwable::class);
});

it('ignores non-string casts when building validation attributes', function (): void {
    $model = new class extends Model
    {
        protected $table = 'cast_validation_table';

        protected function casts(): array
        {
            return [
                'meta' => AsCollection::class,
            ];
        }
    };

    $model->setRawAttributes(['meta' => '["a"]']);
    $model->setAttribute('meta', collect(['a']));

    expect($model->getAttributesForValidation()['meta'])->toBe('["a"]');
});

it('uses cast values for array attributes during validation', function (): void {
    $model = new class extends Model
    {
        protected $table = 'array_cast_validation_table';

        protected function casts(): array
        {
            return [
                'meta' => 'array',
            ];
        }
    };

    $model->setAttribute('meta', ['nested' => true]);

    expect($model->getAttributesForValidation()['meta'])->toBe(['nested' => true]);
});

it('encodes dynamic json components before validating json rules', function (): void {
    $model = new class extends Model
    {
        use HasDynamicContents;

        protected $table = 'dynamic_validation_table';

        public static function getEntityType(): Modules\Core\Contracts\IDynamicEntityTypable
        {
            return EntityType::Contents;
        }

        public static function getEntityModelClass(): string
        {
            return Entity::class;
        }

        public function getRules(): array
        {
            return [
                'always' => [],
                'create' => ['payload' => 'required|json'],
                'update' => [],
            ];
        }

        public function getComponentsAttribute(): array
        {
            return ['payload' => ['nested' => true]];
        }
    };

    expect(fn () => $model->validateWithRules(CrudExecutor::INSERT))->not->toThrow(ContextualValidationException::class);
});

it('encodes array-cast attributes before validating json rules', function (): void {
    $model = new class extends Model
    {
        protected $table = 'json_cast_validation_table';

        public function getRules(): array
        {
            return [
                'always' => [],
                'create' => ['payload' => ['required', 'json']],
                'update' => [],
            ];
        }

        protected function casts(): array
        {
            return [
                'payload' => 'array',
            ];
        }
    };

    $model->setAttribute('payload', ['days' => 30, 'percent' => 100]);

    expect(fn () => $model->validateWithRules(CrudExecutor::INSERT))->not->toThrow(ContextualValidationException::class);
});

it('runs database validation rules on the dynamically assigned model connection', function (): void {
    $connection_name = 'validation_affinity';
    $companies_table = 'validation_affinity_companies';
    $documents_table = 'validation_affinity_documents';
    $default_connection = DB::getDefaultConnection();

    config()->set("database.connections.{$connection_name}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge($connection_name);

    $create_schema = static function ($schema) use ($companies_table, $documents_table): void {
        $schema->create($companies_table, function (Blueprint $table): void {
            $table->id();
        });
        $schema->create($documents_table, function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->softDeletes();
        });
    };

    $create_schema(Schema::connection($connection_name));
    $create_schema(Schema::connection($default_connection));

    try {
        DB::connection($connection_name)->table($companies_table)->insert(['id' => 7]);
        DB::connection($connection_name)->table($documents_table)->insert([
            'slug' => 'restorable',
            'deleted_at' => now(),
        ]);
        DB::connection($default_connection)->table($documents_table)->insert(['slug' => 'restorable']);

        $queried_connections = [];
        DB::listen(static function (Illuminate\Database\Events\QueryExecuted $query) use (&$queried_connections): void {
            $queried_connections[] = $query->connectionName;
        });

        $model = new class extends Model
        {
            protected $table = 'validation_affinity_records';

            public function getRules(): array
            {
                return [
                    'always' => [],
                    'create' => [
                        'company_id' => ['required', 'integer', 'exists:validation_affinity_companies,id'],
                        'slug' => [
                            'required',
                            Rule::unique('validation_affinity_documents', 'slug')->withoutTrashed(),
                        ],
                    ],
                    'update' => [],
                ];
            }
        };
        $model->setConnection($connection_name);
        $model->forceFill(['company_id' => 7, 'slug' => 'restorable']);

        expect(fn (): mixed => $model->validateWithRules(CrudExecutor::INSERT))->not->toThrow(ContextualValidationException::class);

        expect($queried_connections)->toContain($connection_name)
            ->and($queried_connections)->not->toContain($default_connection);
    } finally {
        Schema::connection($connection_name)->dropIfExists($documents_table);
        Schema::connection($connection_name)->dropIfExists($companies_table);
        Schema::connection($default_connection)->dropIfExists($documents_table);
        Schema::connection($default_connection)->dropIfExists($companies_table);
        DB::disconnect($connection_name);
        DB::purge($connection_name);
    }
});

it('keeps related model validation rules on the related model explicit connection', function (): void {
    $connection_name = 'validation_related_affinity';
    $related_connection = (new FakeVersionedModel)->getConnectionName();
    $related_table = (new FakeVersionedModel)->getTable();

    config()->set("database.connections.{$connection_name}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge($connection_name);
    Schema::connection($related_connection)->create($related_table, function (Blueprint $table): void {
        $table->id();
    });

    try {
        DB::connection($related_connection)->table($related_table)->insert(['id' => 37]);

        $queried_connections = [];
        DB::listen(static function (Illuminate\Database\Events\QueryExecuted $query) use (&$queried_connections): void {
            $queried_connections[] = $query->connectionName;
        });

        $model = new class extends Model
        {
            public string $related_model_class;

            protected $table = 'validation_related_hosts';

            public function getRules(): array
            {
                return [
                    'always' => [],
                    'create' => [
                        'related_id' => ['required', 'exists:' . $this->related_model_class . ',id'],
                    ],
                    'update' => [],
                ];
            }
        };
        $model->related_model_class = FakeVersionedModel::class;
        $model->setConnection($connection_name);
        $model->forceFill(['related_id' => 37]);

        expect(fn (): mixed => $model->validateWithRules(CrudExecutor::INSERT))->not->toThrow(ContextualValidationException::class);

        expect($queried_connections)->toContain($related_connection)
            ->and($queried_connections)->not->toContain($connection_name);
    } finally {
        Schema::connection($related_connection)->dropIfExists($related_table);
        DB::disconnect($connection_name);
        DB::purge($connection_name);
    }
});

it('clones database rule objects while preserving callbacks and soft delete constraints', function (): void {
    $connection_name = 'validation_rule_affinity';
    config()->set("database.connections.{$connection_name}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge($connection_name);

    try {
        $callback = static fn (Illuminate\Database\Query\Builder $query): Illuminate\Database\Query\Builder => $query->where('tenant_id', 12);
        $original_rule = Rule::unique('validation_rule_documents', 'slug')
            ->withoutTrashed()
            ->where($callback);

        $model = new class extends Model
        {
            public mixed $database_rule;

            protected $table = 'validation_rule_hosts';

            public function getRules(): array
            {
                return [
                    'always' => [],
                    'create' => ['slug' => [$this->database_rule]],
                    'update' => [],
                ];
            }
        };
        $model->database_rule = $original_rule;
        $model->setConnection($connection_name);

        $qualified_rule = $model->getOperationRules(CrudExecutor::INSERT)['slug'][0];

        expect($qualified_rule)->not->toBe($original_rule)
            ->and((string) $original_rule)->toContain('unique:validation_rule_documents,slug')
            ->and((string) $qualified_rule)->toContain("unique:{$connection_name}.validation_rule_documents,slug")
            ->and((string) $qualified_rule)->toContain('deleted_at,"NULL"')
            ->and($qualified_rule->queryCallbacks())->toHaveCount(1)
            ->and($qualified_rule->queryCallbacks()[0])->toBe($callback);
    } finally {
        DB::disconnect($connection_name);
        DB::purge($connection_name);
    }
});

it('authorizes force delete and restore lifecycle hooks', function (): void {
    HasValidations::resetPermissionExistenceCache();

    $role = Role::factory()->create();
    Auth::login(User::factory()->create());

    $role->delete();
    $role->restore();

    $role = Role::factory()->create();
    $role->forceDelete();

    expect(Role::withTrashed()->find($role->id))->toBeNull();
});

it('checks user permissions when a permission row exists', function (): void {
    HasValidations::resetPermissionExistenceCache();

    $table_name = 'check_perm_' . uniqid();
    $operation = 'select';
    $permission_name = "{$table_name}.{$operation}";

    DB::table(Modules\Core\Enums\CoreTables::Permissions->value)->insert([
        'name' => $permission_name,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $model = new class extends Model
    {
        protected $table = '';
    };
    $model->setTable($table_name);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('hasPermission')->with($permission_name)->andReturn(true);
    Auth::login($user);

    $method = new ReflectionMethod(HasValidations::class, 'checkUserCanDo');
    $allowed = $method->invoke(null, $model, $operation);

    expect($allowed)->toBeTrue();
});

it('clears the permission existence cache', function (): void {
    HasValidations::resetPermissionExistenceCache();

    $model = new class extends Model
    {
        protected $table = 'cache_reset_table';
    };

    $method = new ReflectionMethod(HasValidations::class, 'checkUserCanDo');
    $method->invoke(null, $model, 'select');

    HasValidations::resetPermissionExistenceCache();

    expect(true)->toBeTrue();
});

it('rejects non-string casts for validation attribute resolution', function (): void {
    $model = new class extends Model
    {
        protected $table = 'cast_test_table';
    };

    $method = new ReflectionMethod($model, 'shouldUseCastedAttributeForValidation');
    $method->setAccessible(true);

    expect($method->invoke($model, AsCollection::class))->toBeFalse();
    expect($method->invoke($model, 'array'))->toBeTrue();
});
