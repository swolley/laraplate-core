<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\CrudOperationsHarness;


/**
 * @param  array<string, mixed>  $props
 */
function has_crud_operations_make_list_request_data(array $props): ListRequestData
{
    $ref = new ReflectionClass(ListRequestData::class);

    /** @var ListRequestData $data */
    $data = $ref->newInstanceWithoutConstructor();

    $set = function (object $obj, string $prop, mixed $value): void {
        $p = new ReflectionProperty($obj, $prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    };

    foreach ($props as $key => $value) {
        $set($data, $key, $value);
    }

    return $data;
}

beforeEach(function (): void {
    Auth::logout();
});

it('listByPagination applies skip and take from from and to', function (): void {
    $harness = new CrudOperationsHarness();
    $filters = has_crud_operations_make_list_request_data([
        'from' => 2,
        'to' => 3,
    ]);

    $query = User::query();
    $harness->exposeListByPagination($query, $filters, 100);

    expect($query->getQuery()->offset)->toBe(1)
        ->and($query->getQuery()->limit)->toBe(2);
});

it('listByFromTo skips when to is null without executing invalid sqlite offset-only queries', function (): void {
    $harness = new CrudOperationsHarness();
    $filters = has_crud_operations_make_list_request_data([
        'from' => 5,
        'to' => null,
    ]);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('skip')->once()->with(4)->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(new EloquentCollection);

    /** @var Builder<User> $query */
    $harness->exposeListByFromTo($query, $filters, 10);
});

it('listByOthers returns count when count flag is true', function (): void {
    $harness = new CrudOperationsHarness();
    $filters = has_crud_operations_make_list_request_data([
        'limit' => 10,
        'take' => 5,
        'count' => true,
    ]);

    $query = User::query();
    $result = $harness->exposeListByOthers($query, $filters, 42);

    expect($result)->toBe(42);
});

it('listByOthers applies take when limit is set and count is false', function (): void {
    $harness = new CrudOperationsHarness();
    $filters = has_crud_operations_make_list_request_data([
        'limit' => 10,
        'take' => 3,
        'count' => false,
    ]);

    $query = User::query();
    $harness->exposeListByOthers($query, $filters, 100);

    expect($query->getQuery()->limit)->toBe(3);
});

it('applySorting adds order by clauses', function (): void {
    $harness = new CrudOperationsHarness();
    $query = User::query();
    $harness->exposeApplySorting($query, [
        ['field' => 'username', 'direction' => 'desc'],
        ['field' => 'id'],
    ]);

    $orders = $query->getQuery()->orders;

    expect($orders)->toHaveCount(2)
        ->and($orders[0]['column'])->toBe('username')
        ->and($orders[0]['direction'])->toBe('desc')
        ->and($orders[1]['direction'])->toBe('asc');
});

it('getCacheKey includes table, params hash and guest when unauthenticated', function (): void {
    $harness = new CrudOperationsHarness();
    $user = new User;
    $user->setTable('users');

    $key = $harness->exposeGetCacheKey($user, ['a' => 1]);

    expect($key)->toStartWith('crud:users:')
        ->and($key)->toEndWith(':guest');
});

it('getCacheKey includes authenticated user id', function (): void {
    $user = User::factory()->create();
    Auth::login($user);

    $harness = new CrudOperationsHarness();
    $model = new User;
    $model->setTable('users');

    $key = $harness->exposeGetCacheKey($model, []);

    expect($key)->toEndWith(':' . (string) $user->getAuthIdentifier());
});

it('removeNonFillableProperties strips keys not in fillable', function (): void {
    $harness = new CrudOperationsHarness();
    $model = new User;
    $values = ['username' => 'x', 'not_fillable' => 'y'];

    $discarded = $harness->exposeRemoveNonFillableProperties($model, $values);

    expect($values)->not->toHaveKey('not_fillable')
        ->and($discarded)->not->toBeEmpty();
});

it('applyGroupBy returns same collection when group by is empty', function (): void {
    $harness = new CrudOperationsHarness();
    $data = new EloquentCollection([['k' => 'a']]);

    $out = $harness->exposeApplyGroupBy($data, []);

    expect($out)->toBe($data);
});

it('applyGroupBy groups collection by keys', function (): void {
    $harness = new CrudOperationsHarness();
    $data = new EloquentCollection([
        (object) ['type' => 'a'],
        (object) ['type' => 'b'],
        (object) ['type' => 'a'],
    ]);

    $grouped = $harness->exposeApplyGroupBy($data, ['type']);

    expect($grouped)->toHaveKeys(['a', 'b']);
});

it('applyFilter supports like, in, between and default operators', function (): void {
    $harness = new CrudOperationsHarness();

    $q1 = User::query();
    $harness->exposeApplyFilter($q1, 'username', ['value' => 'foo', 'operator' => 'like']);
    expect($q1->toSql())->toContain('like');

    $q2 = User::query();
    $harness->exposeApplyFilter($q2, 'id', ['value' => [1, 2], 'operator' => 'in']);
    expect($q2->toSql())->toContain('in');

    $q3 = User::query();
    $harness->exposeApplyFilter($q3, 'created_at', ['value' => ['2020-01-01', '2020-12-31'], 'operator' => 'between']);
    expect($q3->toSql())->toContain('between');

    $q4 = User::query();
    $harness->exposeApplyFilter($q4, 'id', ['value' => 5, 'operator' => '=']);
    $bindings = $q4->getBindings();
    expect($bindings)->toContain(5);
});
