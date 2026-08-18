<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Models\License;
use Modules\Core\Models\Role;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\DTOs\FacetPage;
use Modules\Core\Services\Crud\DTOs\FacetQuery;
use Modules\Core\Services\Crud\DTOs\FacetSort;
use Modules\Core\Tests\Fixtures\FacetLabelSourceOwner;

function facet_label_request(): Request
{
    return new class extends Request
    {
        public function validated(?string $key = null, mixed $default = null): mixed
        {
            return $key === null ? [] : $default;
        }
    };
}

function facet_label_list_data(Model $model, Request $request, ?FiltersGroup $filters = null): Modules\Core\Casts\ListRequestData
{
    $data = new ReflectionClass(Modules\Core\Casts\ListRequestData::class)->newInstanceWithoutConstructor();

    $set = static function (object $obj, string $prop, mixed $value): void {
        new ReflectionProperty($obj, $prop)->setValue($obj, $value);
    };

    foreach ([
        'request' => $request,
        'mainEntity' => $model->getTable(),
        'primaryKey' => $model->getKeyName(),
        'connection' => $model->getConnectionName(),
        'model' => $model,
        'columns' => [],
        'relations' => [],
        'sort' => [],
        'filters' => $filters,
        'group_by' => [],
        'pagination' => 25,
        'page' => null,
        'skip' => null,
        'take' => null,
        'from' => null,
        'to' => null,
        'limit' => null,
        'count' => false,
    ] as $prop => $value) {
        $set($data, $prop, $value);
    }

    return $data;
}

function facet_label_superadmin(): User
{
    $role = Role::factory()->create(['name' => config('permission.roles.superadmin'), 'guard_name' => 'web']);
    $user = User::factory()->create(['name' => 'zzz-superadmin']);
    $user->assignRole($role);
    auth()->login($user);

    return $user;
}

function facet_label_service(User $actor, FacetQuery $facet): FacetPage
{
    $request = facet_label_request();
    $request->setUserResolver(fn (): User => $actor);
    $base = facet_label_list_data(new FacetLabelSourceOwner, $request);

    return app(CrudService::class)->facetValues($base, $facet);
}

it('resolves a declared label source through a foreign key with no BelongsTo', function (): void {
    $admin = facet_label_superadmin();

    $license = License::factory()->create(['uuid' => '00000000-0000-0000-0000-0000000000aa']);
    User::factory()->count(2)->create(['license_id' => $license->id]);

    $page = facet_label_service($admin, new FacetQuery(
        groupBy: 'license_id',
        fields: ['lic.uuid'],
        sort: FacetSort::CountDesc,
    ));

    $row = collect($page->values)->firstWhere('key', $license->id);

    expect($row['count'])->toBe(2)
        ->and($row['attributes'])->toBe(['lic.uuid' => $license->uuid]);
});

it('sorts and searches a declared label source by its label', function (): void {
    $admin = facet_label_superadmin();

    $first = License::factory()->create(['uuid' => '00000000-0000-0000-0000-000000000001']);
    $second = License::factory()->create(['uuid' => '00000000-0000-0000-0000-000000000002']);
    User::factory()->count(2)->create(['license_id' => $first->id]);
    User::factory()->count(3)->create(['license_id' => $second->id]);

    $sorted = facet_label_service($admin, new FacetQuery(
        groupBy: 'license_id',
        fields: ['lic.uuid'],
        sort: FacetSort::LabelAsc,
        labelField: 'lic.uuid',
    ));

    expect(collect($sorted->values)->pluck('key')->filter()->values()->all())->toBe([$first->id, $second->id]);

    $searched = facet_label_service($admin, new FacetQuery(
        groupBy: 'license_id',
        labelField: 'lic.uuid',
        search: '000001',
    ));

    expect($searched->distinctValues)->toBe(1)
        ->and(collect($searched->values)->pluck('key')->all())->toBe([$first->id]);
});
