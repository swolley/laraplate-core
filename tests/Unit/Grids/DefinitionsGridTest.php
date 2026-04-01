<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Core\Grids\Components\Field;
use Modules\Core\Grids\Definitions\HasPath;
use Modules\Core\Grids\Definitions\HasValidations;
use Modules\Core\Grids\Definitions\PivotRelationInfo;
use Modules\Core\Grids\Definitions\RelationInfo;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

final class DefinitionsModelStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    protected $fillable = ['name'];

    public function getRules(): array
    {
        return [
            'name' => ['required'],
        ];
    }

    protected function casts(): array
    {
        return [];
    }
}

final class HasPathHarness
{
    use HasPath;

    public function __construct(string $path, string $name)
    {
        $this->path = $path;
        $this->name = $name;
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function split(string $fullpath): array
    {
        $method = new ReflectionMethod(self::class, 'splitPath');
        $method->setAccessible(true);

        /** @var array{0:string,1:string} $result */
        return $method->invoke(null, $fullpath);
    }
}

final class HasValidationsHarness
{
    use HasValidations;
}

it('covers HasPath getter methods and split helper', function (): void {
    $harness = new HasPathHarness('users.profile', 'email');
    [$path, $name] = HasPathHarness::split('Users.Profile.Email');

    expect($harness->getName())->toBe('email')
        ->and($harness->getPath())->toBe('users.profile')
        ->and($harness->getFullName())->toBe('users.profile.email')
        ->and($path)->toBe('users.profile')
        ->and($name)->toBe('email');
});

it('covers HasValidations with string and array rules', function (): void {
    $harness = new HasValidationsHarness();

    $harness->validation('required|string|max:255');
    expect($harness->getValidation())->toBe(['required', 'string', 'max:255']);

    $harness->validation(['nullable', 'email']);
    expect($harness->getValidation())->toBe(['nullable', 'email']);
});

it('applies write formatters through HasFormatters static helper', function (): void {
    $field = new Field('users', 'name', null, model: new DefinitionsModelStub());
    $field->setFormatter(static fn (mixed $value): string => mb_strtoupper((string) $value));

    $result = Field::applySetFormatter(
        new Collection([$field]),
        ['users.name' => 'john'],
    );

    expect($result['users.name'])->toBe('JOHN');
});

it('covers read and write formatter accessors', function (): void {
    $field = new Field('users', 'name', null, model: new DefinitionsModelStub());
    $read = static fn (mixed $value): string => (string) $value;
    $write = static fn (mixed $value): string => mb_strtolower((string) $value);

    $field->getFormatter($read)->setFormatter($write);

    expect($field->hasReadFormatter())->toBeTrue()
        ->and($field->hasWriteFormatter())->toBeTrue()
        ->and($field->getReadFormatter())->toBe($read)
        ->and($field->getWriteFormatter())->toBe($write);
});

it('returns relation info getters', function (): void {
    $relation = new RelationInfo('belongsTo', 'role', 'Modules\\Core\\Models\\Role', 'roles', 'role_id', 'id');

    expect($relation->getType())->toBe('belongsTo')
        ->and($relation->getName())->toBe('role')
        ->and($relation->getModel())->toBe('Modules\\Core\\Models\\Role')
        ->and($relation->getTable())->toBe('roles')
        ->and($relation->getForeignKey())->toBe('role_id')
        ->and($relation->getOwnerKey())->toBe('id');
});

it('builds pivot relation info from base relation', function (): void {
    $base = new RelationInfo('belongsToMany', 'permissions', 'Modules\\Core\\Models\\Permission', 'permissions', 'role_id', 'permission_id');
    $pivot = PivotRelationInfo::fromRelationInfo($base, 'role_has_permissions', 'role_id', 'permission_id');

    expect($pivot->getType())->toBe('belongsToMany')
        ->and($pivot->getName())->toBe('permissions')
        ->and($pivot->getPivotTable())->toBe('role_has_permissions')
        ->and($pivot->getPivotOwnerKey())->toBe('role_id')
        ->and($pivot->getPivotForeignKey())->toBe('permission_id');
});
