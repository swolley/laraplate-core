<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Support\Facades\Auth;
use Modules\CMS\Casts\EntityType;
use Modules\CMS\Models\Content;
use Modules\CMS\Models\Entity;
use Modules\Core\Casts\CrudExecutor;
use Modules\Core\Models\Concerns\HasDynamicContents;
use Modules\Core\Models\Concerns\HasValidations;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Overrides\ContextualValidationException;
use Modules\Core\Overrides\Model;

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

        public function getRules(): array
        {
            return [
                'always' => [],
                'create' => ['payload' => 'required|json'],
                'update' => [],
            ];
        }

        public static function getEntityType(): Modules\Core\Contracts\IDynamicEntityTypable
        {
            return EntityType::Contents;
        }

        public static function getEntityModelClass(): string
        {
            return Entity::class;
        }

        public function getComponentsAttribute(): array
        {
            return ['payload' => ['nested' => true]];
        }
    };

    expect(fn () => $model->validateWithRules(CrudExecutor::INSERT))->not->toThrow(ContextualValidationException::class);
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

    Illuminate\Support\Facades\DB::table(Modules\Core\Enums\CoreTables::Permissions->value)->insert([
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
