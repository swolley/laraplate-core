<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Models\License;
use Modules\Core\Models\Role;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\DTOs\FacetPage;
use Modules\Core\Services\Crud\DTOs\FacetQuery;
use Modules\Core\Services\Crud\DTOs\FacetSort;

/**
 * @param  array<string,mixed>  $validated
 */
function facet_values_request(array $validated = []): Request
{
    return new class($validated) extends Request
    {
        /**
         * @param  array<string,mixed>  $validated
         */
        public function __construct(private readonly array $validated)
        {
            parent::__construct();
        }

        public function validated(?string $key = null, mixed $default = null): mixed
        {
            return $key === null ? $this->validated : ($this->validated[$key] ?? $default);
        }
    };
}

function facet_values_list_data(Model $model, Request $request, ?FiltersGroup $filters = null): Modules\Core\Casts\ListRequestData
{
    $data = new ReflectionClass(Modules\Core\Casts\ListRequestData::class)->newInstanceWithoutConstructor();

    $set = static function (object $obj, string $prop, mixed $value): void {
        $p = new ReflectionProperty($obj, $prop);
        $p->setValue($obj, $value);
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

function facet_values_superadmin(): User
{
    $role = Role::factory()->create(['name' => config('permission.roles.superadmin'), 'guard_name' => 'web']);
    $user = User::factory()->create(['name' => 'zzz-superadmin']);
    $user->assignRole($role);
    auth()->login($user);

    return $user;
}

function facet_values_service(User $actor, ?FiltersGroup $filters, FacetQuery $facet): FacetPage
{
    $request = facet_values_request();
    $request->setUserResolver(fn (): User => $actor);
    $base = facet_values_list_data(new User, $request, $filters);

    return app(CrudService::class)->facetValues($base, $facet);
}

it('groups, paginates and counts open facet values with the double counter', function (): void {
    $admin = facet_values_superadmin();
    User::factory()->count(3)->create(['name' => 'Ada']);
    User::factory()->count(2)->create(['name' => 'Bob']);
    User::factory()->create(['name' => 'Cy']);

    $page = facet_values_service($admin, null, new FacetQuery(groupBy: 'name', sort: FacetSort::CountDesc));

    // 4 distinct names (Ada, Bob, Cy, superadmin), most frequent first.
    expect($page->distinctValues)->toBe(4)
        ->and($page->values[0]['key'])->toBe('Ada')
        ->and($page->values[0]['count'])->toBe(3)
        ->and($page->values[0]['total'])->toBe(3)
        ->and($page->values[1]['key'])->toBe('Bob')
        ->and($page->values[1]['count'])->toBe(2);
});

it('keeps total unfiltered while count reflects a base filter on another field', function (): void {
    $admin = facet_values_superadmin();
    User::factory()->create(['name' => 'Ada', 'email' => 'a-keep@example.test']);
    User::factory()->create(['name' => 'Ada', 'email' => 'a-drop@example.test']);
    User::factory()->create(['name' => 'Bob', 'email' => 'b-keep@example.test']);

    $filters = new FiltersGroup([new Filter('email', '%keep%', FilterOperator::Like)]);
    $page = facet_values_service($admin, $filters, new FacetQuery(groupBy: 'name', sort: FacetSort::KeyAsc));

    $byKey = collect($page->values)->keyBy('key');

    expect($byKey['Ada']['total'])->toBe(2)
        ->and($byKey['Ada']['count'])->toBe(1)
        ->and($byKey['Bob']['total'])->toBe(1)
        ->and($byKey['Bob']['count'])->toBe(1);
});

it('paginates the facet itself and reports the distinct-value count', function (): void {
    $admin = facet_values_superadmin();
    User::factory()->count(3)->create(['name' => 'Ada']);
    User::factory()->count(2)->create(['name' => 'Bob']);

    $first = facet_values_service($admin, null, new FacetQuery(groupBy: 'name', perPage: 1, page: 1, sort: FacetSort::CountDesc));
    $second = facet_values_service($admin, null, new FacetQuery(groupBy: 'name', perPage: 1, page: 2, sort: FacetSort::CountDesc));

    expect($first->values)->toHaveCount(1)
        ->and($first->values[0]['key'])->toBe('Ada')
        ->and($first->distinctValues)->toBe(3) // Ada, Bob, superadmin
        ->and($second->values)->toHaveCount(1)
        ->and($second->values[0]['key'])->toBe('Bob');
});

it('searches within the facet values by key', function (): void {
    $admin = facet_values_superadmin();
    User::factory()->create(['name' => 'Ada']);
    User::factory()->create(['name' => 'Adam']);
    User::factory()->create(['name' => 'Bob']);

    // 'Ada' matches both 'Ada' and 'Adam' but not the seeded superadmin, so the
    // facet's own value search is exercised without colliding with 'superadmin'.
    $page = facet_values_service($admin, null, new FacetQuery(groupBy: 'name', search: 'Ada', sort: FacetSort::KeyAsc));

    expect($page->distinctValues)->toBe(2)
        ->and(collect($page->values)->pluck('key')->all())->toBe(['Ada', 'Adam']);
});

it('resolves display fields per key in the key/label two-step', function (): void {
    $admin = facet_values_superadmin();
    User::factory()->count(2)->create(['name' => 'Ada']);

    $page = facet_values_service($admin, null, new FacetQuery(groupBy: 'name', fields: ['name'], sort: FacetSort::CountDesc));

    $ada = collect($page->values)->firstWhere('key', 'Ada');

    expect($ada['attributes'])->toBe(['name' => 'Ada']);
});

it('resolves a single-hop BelongsTo label whose foreign key is the group key', function (): void {
    $admin = facet_values_superadmin();

    $license = License::factory()->create(['uuid' => Str::uuid()->toString()]);
    User::factory()->count(2)->create(['license_id' => $license->id]);

    // Group by the foreign key, label from the related license.
    $page = facet_values_service($admin, null, new FacetQuery(
        groupBy: 'license_id',
        fields: ['license.uuid'],
        sort: FacetSort::CountDesc,
    ));

    $row = collect($page->values)->firstWhere('key', $license->id);

    expect($row['count'])->toBe(2)
        ->and($row['attributes'])->toBe(['license.uuid' => $license->uuid]);
});

it('skips relation labels that are not a BelongsTo keyed by the group key', function (): void {
    $admin = facet_values_superadmin();
    User::factory()->count(2)->create(['name' => 'Ada']);

    // `license` is a BelongsTo keyed by license_id, not by `name`, so it cannot be
    // resolved from a name-grouped page: its field is dropped, not guessed.
    $page = facet_values_service($admin, null, new FacetQuery(
        groupBy: 'name',
        fields: ['license.uuid'],
        sort: FacetSort::CountDesc,
    ));

    $ada = collect($page->values)->firstWhere('key', 'Ada');

    expect($ada['attributes'])->toBe([]);
});
