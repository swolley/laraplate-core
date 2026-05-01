<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\Sort;
use Modules\Core\Casts\SortDirection;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\Fixtures\QueryBuilderOwner;


/**
 * @param  array<int,Column>  $columns
 * @param  array<int,string|array{name:string}>  $relations
 * @param  array<int,Sort>  $sort
 */
function qb_fullcov_list_data(array $columns, array $relations = [], array $sort = [], ?FiltersGroup $filters = null): ListRequestData
{
    $ref = new ReflectionClass(ListRequestData::class);

    /** @var ListRequestData $data */
    $data = $ref->newInstanceWithoutConstructor();

    $set = function (object $obj, string $prop, mixed $value): void {
        $p = new ReflectionProperty($obj, $prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    };

    $set($data, 'columns', $columns);
    $set($data, 'relations', $relations);
    $set($data, 'sort', $sort);
    $set($data, 'filters', $filters);

    return $data;
}

it('prepareQuery stacks multiple aggregates on the same relation key', function (): void {
    $query = User::query();
    $request_data = qb_fullcov_list_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.id', ColumnType::SUM),
            new Column('users.roles.id', ColumnType::AVG),
        ],
        relations: ['roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $sql = mb_strtolower($query->toSql());

    expect($sql)->toContain('sum');
    expect($sql)->toContain('avg');
});

it('prepareQuery stacks multiple column selections on the same relation', function (): void {
    $query = User::query();
    $request_data = qb_fullcov_list_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
            new Column('users.roles.guard_name', ColumnType::COLUMN),
        ],
        relations: ['roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    expect($query->getEagerLoads())->toHaveKey('roles');
});

it('prepareQuery applies nested relation count aggregate inside eager role callback', function (): void {
    $query = User::query();
    $request_data = qb_fullcov_list_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
            new Column('users.roles.permissions.id', ColumnType::COUNT),
        ],
        relations: ['roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $user = User::factory()->create();
    $relation = $user->roles();
    $query->getEagerLoads()['roles']($relation);

    $sql = mb_strtolower($relation->getQuery()->toSql());

    expect($sql)->toContain('permissions');
    expect($sql)->toContain('count');
});

it('extractRelationFilters walks nested filter groups and skips main-entity-only properties', function (): void {
    $query = User::query();
    $request_data = qb_fullcov_list_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
        ],
        relations: ['roles'],
        filters: new FiltersGroup([
            new FiltersGroup([
                new Filter('users.username', 'alpha', FilterOperator::EQUALS),
                new Filter('roles.name', 'sales', FilterOperator::EQUALS),
            ], WhereClause::AND),
            new Filter('id', 1, FilterOperator::GREAT),
        ]),
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    expect($query->getEagerLoads())->toHaveKey('roles');
});

it('prepareQuery applies sort branches for main column orderBy, relation method skip, and single-segment relation path', function (): void {
    $query = User::query();
    $request_data = qb_fullcov_list_data(
        columns: [new Column('users.id', ColumnType::COLUMN)],
        relations: [],
        sort: [
            new Sort('username', SortDirection::ASC),
            new Sort('roles', SortDirection::DESC),
        ],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    expect($query->getQuery()->orders)->not->toBeEmpty();
});

it('prepareQuery skips relation sort when stripped sort path has no relation segment', function (): void {
    if (! Schema::hasTable('cov_a')) {
        Schema::create('cov_a', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    SchemaInspector::getInstance()->clearAll();

    $model = new class extends Model
    {
        protected $table = 'cov_a';

        protected $guarded = [];
    };

    $model->newQuery()->create([]);

    $query = $model->newQuery();
    $request_data = qb_fullcov_list_data(
        columns: [new Column('cov_a.id', ColumnType::COLUMN)],
        relations: [],
        sort: [new Sort('xcov_a.ky', SortDirection::ASC)],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    expect($query->getQuery()->orders)->toBeEmpty();
});

it('prepareQuery owner relation uses crudComputedDependencies merge and nested with()', function (): void {
    if (! Schema::hasTable('qb_cov_items')) {
        Schema::create('qb_cov_items', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->timestamps();
        });
    }

    SchemaInspector::getInstance()->clearAll();

    $item_model = new class extends Model
    {
        protected $table = 'qb_cov_items';

        protected $guarded = [];

        public function owner(): BelongsTo
        {
            return $this->belongsTo(QueryBuilderOwner::class, 'owner_id');
        }
    };

    $owner = User::factory()->create(['username' => 'cov_owner', 'name' => 'Cov Owner']);
    License::factory()->create();
    $owner->forceFill(['license_id' => License::query()->firstOrFail()->getKey()])->save();

    $item_model->newQuery()->create([
        'title' => 'row',
        'owner_id' => $owner->getKey(),
    ]);

    $query = $item_model->newQuery();
    $request_data = qb_fullcov_list_data(
        columns: [
            new Column('qb_cov_items.title', ColumnType::COLUMN),
            new Column('qb_cov_items.owner.username', ColumnType::COLUMN),
            new Column('qb_cov_items.owner.nick', ColumnType::APPEND),
        ],
        relations: ['owner'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $withs = $query->getEagerLoads();
    expect($withs)->toHaveKey('owner');

    $item = $item_model->newQuery()->firstOrFail();
    $owner_relation = $item->owner();
    $withs['owner']($owner_relation);

    $eager = $owner_relation->getEagerLoads();
    expect($eager)->toHaveKey('license');
});

it('prepareQuery drops owner relation column list when append has no crudComputedDependencies entry', function (): void {
    if (! Schema::hasTable('qb_cov_items')) {
        Schema::create('qb_cov_items', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->timestamps();
        });
    }

    SchemaInspector::getInstance()->clearAll();

    $item_model = new class extends Model
    {
        protected $table = 'qb_cov_items';

        protected $guarded = [];

        public function owner(): BelongsTo
        {
            return $this->belongsTo(QueryBuilderOwner::class, 'owner_id');
        }
    };

    $owner = User::factory()->create(['username' => 'cov_force_all', 'name' => 'Cov']);
    License::factory()->create();
    $owner->forceFill(['license_id' => License::query()->firstOrFail()->getKey()])->save();

    $item_model->newQuery()->create([
        'title' => 'row',
        'owner_id' => $owner->getKey(),
    ]);

    $query = $item_model->newQuery();
    $request_data = qb_fullcov_list_data(
        columns: [
            new Column('qb_cov_items.title', ColumnType::COLUMN),
            new Column('qb_cov_items.owner.username', ColumnType::COLUMN),
            new Column('qb_cov_items.owner.orphan_append', ColumnType::APPEND),
        ],
        relations: ['owner'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    expect($query->getEagerLoads())->toHaveKey('owner');

    $item = $item_model->newQuery()->firstOrFail();
    $query->getEagerLoads()['owner']($item->owner());
});

it('resolveComputedDependencies forces select-all when a dependency key is missing', function (): void {
    if (! Schema::hasTable('qb_cov_dep')) {
        Schema::create('qb_cov_dep', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    SchemaInspector::getInstance()->clearAll();

    $model = new class extends Model
    {
        protected $table = 'qb_cov_dep';

        protected $guarded = [];

        public function getKnownAttribute(): string
        {
            return (string) $this->label;
        }

        /**
         * @return array<string, array{columns: array<int, string>}>
         */
        public function crudComputedDependencies(): array
        {
            return [
                'known' => ['columns' => ['label']],
            ];
        }
    };

    $model->newQuery()->create(['label' => 'x']);

    $query = $model->newQuery();
    $request_data = qb_fullcov_list_data([
        new Column('qb_cov_dep.label', ColumnType::COLUMN),
        new Column('qb_cov_dep.known', ColumnType::APPEND),
        new Column('qb_cov_dep.missing_append', ColumnType::APPEND),
    ]);

    (new QueryBuilder())->prepareQuery($query, $request_data);

    /** @var array<int, string>|null $selected */
    $selected = $query->getQuery()->columns;

    expect($selected)->not->toBeNull();
    expect($selected)->toContain('qb_cov_dep.*');
});

it('exercises QueryBuilder private helpers via reflection for remaining branches', function (): void {
    $qb = new QueryBuilder();
    $ref = new ReflectionClass($qb);

    $apply_columns = $ref->getMethod('applyColumnsToSelect');
    $apply_columns->setAccessible(true);

    $user = User::factory()->create();
    $relation = $user->roles();
    $relation_columns = [new Column('id', ColumnType::SUM)];
    $apply_columns->invokeArgs($qb, [$relation, &$relation_columns]);

    $apply_agg = $ref->getMethod('applyAggregatesToQuery');
    $apply_agg->setAccessible(true);
    $agg = ['other_relation.sum' => [new Column('id', ColumnType::SUM)]];
    $apply_agg->invokeArgs($qb, [$relation, &$agg, 'roles']);
    expect($agg)->toHaveKey('other_relation.sum');

    $apply_appends = $ref->getMethod('applyModelAppends');
    $apply_appends->setAccessible(true);
    $apply_appends->invoke($qb, $user, []);

    $merge = $ref->getMethod('mergeComputedDependencies');
    $merge->setAccessible(true);
    $rel_cols = ['roles' => [new Column('name', ColumnType::COLUMN)]];
    $merge->invokeArgs($qb, [&$rel_cols, 'roles', ['name', 'guard_name']]);
    expect(count($rel_cols['roles']))->toBe(2);

    $rel_cols_dup = ['roles' => [new Column('name', ColumnType::COLUMN)]];
    $merge->invokeArgs($qb, [&$rel_cols_dup, 'roles', ['name']]);
    expect(count($rel_cols_dup['roles']))->toBe(1);

    $merge->invokeArgs($qb, [&$rel_cols_dup, 'new_rel', ['id']]);
    expect($rel_cols_dup)->toHaveKey('new_rel');

    $extract_computed = $ref->getMethod('extractComputedColumns');
    $extract_computed->setAccessible(true);
    $computed = $extract_computed->invoke($qb, 'users', [
        new Column('users.roles.fake_append', ColumnType::APPEND),
    ]);
    expect($computed['relations']['roles']['append'])->toContain('fake_append');

    $add_fk = $ref->getMethod('addForeignKeysToSelectedColumns');
    $add_fk->setAccessible(true);
    $main = new User();
    $prefilled = ['id', 'username', 'license_id'];
    $add_fk->invokeArgs($qb, [User::query(), &$prefilled, $main, 'users', false]);
    $count_after_first = count($prefilled);
    $add_fk->invokeArgs($qb, [User::query(), &$prefilled, $main, 'users', false]);
    expect(count($prefilled))->toBe($count_after_first);

    $extract_for_relation = $ref->getMethod('extractFiltersForRelation');
    $extract_for_relation->setAccessible(true);
    $user_for_filter = User::factory()->create();
    $only_main_filter = new FiltersGroup([
        new Filter('users.username', 'x', FilterOperator::EQUALS),
    ], WhereClause::AND);
    expect($extract_for_relation->invoke($qb, $user_for_filter, $only_main_filter, 'roles'))->toBeNull();
});
