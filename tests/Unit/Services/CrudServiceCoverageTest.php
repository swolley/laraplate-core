<?php

declare(strict_types=1);

use Approval\Traits\RequiresApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\DetailRequestData;
use Modules\Core\Casts\HistoryRequestData;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Casts\TreeRequestData;
use Modules\Core\Locking\Exceptions\AlreadyLockedException;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\LaravelTestCase;
use Overtrue\LaravelVersionable\Versionable;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

uses(LaravelTestCase::class);

function crud_cov_set(object $obj, string $prop, mixed $value): void
{
    $p = new ReflectionProperty($obj, $prop);
    $p->setAccessible(true);
    $p->setValue($obj, $value);
}

/**
 * @param  class-string  $class
 * @param  array<int,Column>  $columns
 */
function crud_cov_make_request_data(string $class, Model $model, Request $request, string|array $primary_key, array $columns = []): object
{
    $ref = new ReflectionClass($class);
    $data = $ref->newInstanceWithoutConstructor();

    crud_cov_set($data, 'request', $request);
    crud_cov_set($data, 'mainEntity', $model->getTable());
    crud_cov_set($data, 'primaryKey', $primary_key);
    crud_cov_set($data, 'model', $model);
    crud_cov_set($data, 'connection', $model->getConnectionName());

    if (property_exists($data, 'columns')) {
        crud_cov_set($data, 'columns', $columns);
    }

    if (property_exists($data, 'relations')) {
        crud_cov_set($data, 'relations', []);
    }

    if (property_exists($data, 'sort')) {
        crud_cov_set($data, 'sort', []);
    }

    if (property_exists($data, 'filters')) {
        crud_cov_set($data, 'filters', null);
    }

    if (property_exists($data, 'group_by')) {
        crud_cov_set($data, 'group_by', []);
    }

    return $data;
}

/**
 * @param  array<string,mixed>  $validated
 */
function crud_cov_validated_request(array $validated = []): Request
{
    return new class($validated) extends Request
    {
        /**
         * @param  array<string,mixed>  $validated
         */
        public function __construct(private readonly array $validated)
        {
            parent::__construct($validated);
        }

        public function validated(?string $key = null, mixed $default = null): mixed
        {
            if ($key === null) {
                return $this->validated;
            }

            return $this->validated[$key] ?? $default;
        }
    };
}

function crud_cov_login_superadmin(): User
{
    $superadmin_role = Role::factory()->create([
        'name' => config('permission.roles.superadmin'),
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'username' => 'crud_cov_admin_' . uniqid(),
        'email' => 'crud_cov_admin_' . uniqid() . '@example.com',
    ]);
    $user->assignRole($superadmin_role);
    auth()->login($user);

    return $user;
}

function crud_cov_make_modify_data(Model $model, Request $request, array $changes = [], string|array|int $primary_key = 'id'): ModifyRequestData
{
    $modify_ref = new ReflectionClass(ModifyRequestData::class);

    /** @var ModifyRequestData $modify */
    $modify = $modify_ref->newInstanceWithoutConstructor();
    crud_cov_set($modify, 'request', $request);
    crud_cov_set($modify, 'mainEntity', $model->getTable());

    /** @var string|array $pk */
    $pk = is_int($primary_key) ? (string) $primary_key : $primary_key;
    crud_cov_set($modify, 'primaryKey', $pk);
    crud_cov_set($modify, 'model', $model);
    crud_cov_set($modify, 'connection', $model->getConnectionName());
    crud_cov_set($modify, 'changes', $changes);

    return $modify;
}

it('list applies group_by branch when requested', function (): void {
    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $request = crud_cov_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $model = new User();
    $data = crud_cov_make_request_data(
        ListRequestData::class,
        $model,
        $request,
        $model->getKeyName(),
        [new Column('users.lang', ColumnType::COLUMN)],
    );

    crud_cov_set($data, 'page', null);
    crud_cov_set($data, 'from', null);
    crud_cov_set($data, 'limit', null);
    crud_cov_set($data, 'count', false);
    crud_cov_set($data, 'pagination', 25);
    crud_cov_set($data, 'group_by', ['lang']);

    User::factory()->create(['lang' => 'en']);
    User::factory()->create(['lang' => 'en']);
    User::factory()->create(['lang' => 'it']);

    $result = $service->list($data);

    expect($result->data)->toBeInstanceOf(Illuminate\Support\Collection::class);
    expect($result->data->keys()->all())->toContain('en');
});

it('detail throws when primary key is missing', function (): void {
    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $request = crud_cov_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $model = new User();
    $data = crud_cov_make_request_data(
        DetailRequestData::class,
        $model,
        $request,
        $model->getKeyName(),
        [new Column('users.id', ColumnType::COLUMN)],
    );

    $service->detail($data);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class, 'Primary key is required for detail.');

it('getModelKeyValue handles composite key request data', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $model = new class extends Model
    {
        public function getKeyName(): array
        {
            return ['tenant_id', 'id'];
        }
    };

    $modify_ref = new ReflectionClass(ModifyRequestData::class);
    $modify = $modify_ref->newInstanceWithoutConstructor();
    crud_cov_set($modify, 'model', $model);
    crud_cov_set($modify, 'changes', ['tenant_id' => 11, 'id' => 22]);

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('getModelKeyValue');
    $method->setAccessible(true);
    $key_value = $method->invoke($service, $modify);

    expect($key_value)->toBe(['tenant_id' => 11, 'id' => 22]);
});

it('tree selects relation type and switches between sole and get modes', function (): void {
    if (! Schema::hasTable('crud_cov_tree')) {
        Schema::create('crud_cov_tree', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });
    }

    $tree_model = new class extends Model
    {
        use HasRecursiveRelationships;

        protected $table = 'crud_cov_tree';

        protected $guarded = [];
    };

    $root = $tree_model->newQuery()->create(['name' => 'root']);

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $request_single = Request::create('/tree', 'GET', ['id' => $root->getKey()]);
    $request_single->query->set('id', $root->getKey());
    $request_single->setUserResolver(fn () => $superadmin);
    $data_single = crud_cov_make_request_data(TreeRequestData::class, $tree_model, $request_single, 'id', [
        new Column('crud_cov_tree.id', ColumnType::COLUMN),
    ]);
    crud_cov_set($data_single, 'parents', true);
    crud_cov_set($data_single, 'children', true);
    $single_result = $service->tree($data_single);
    expect($single_result->data)->toBeInstanceOf(Model::class);

    $tree_model->newQuery()->create(['name' => 'child', 'parent_id' => $root->getKey()]);

    $request_many = Request::create('/tree', 'GET');
    $request_many->setUserResolver(fn () => $superadmin);
    $data_many = crud_cov_make_request_data(TreeRequestData::class, $tree_model, $request_many, 'id', [
        new Column('crud_cov_tree.id', ColumnType::COLUMN),
    ]);
    crud_cov_set($data_many, 'parents', false);
    crud_cov_set($data_many, 'children', true);
    $many_result = $service->tree($data_many);
    expect($many_result->data)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
});

it('activate and inactivate use doActivateOperation path', function (): void {
    if (! Schema::hasTable('crud_cov_soft')) {
        Schema::create('crud_cov_soft', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    $soft_model = new class extends Model
    {
        use SoftDeletes;

        protected $table = 'crud_cov_soft';

        protected $guarded = [];
    };

    $record = $soft_model->newQuery()->create(['name' => 'x']);
    $record->delete();

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $request = Request::create('/modify', 'POST', ['id' => $record->getKey()]);
    $request->request->set('id', $record->getKey());
    $request->setUserResolver(fn () => $superadmin);
    $modify = crud_cov_make_modify_data($soft_model, $request, []);

    $activate_result = $service->activate($modify);
    expect($activate_result->data)->toBeInstanceOf(Model::class);

    $inactivate_result = $service->inactivate($modify);
    expect($inactivate_result->data)->toBeInstanceOf(Model::class);
});

it('insert, update and delete cover core mutation flows', function (): void {
    if (! Schema::hasTable('crud_cov_items')) {
        Schema::create('crud_cov_items', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    $item_model = new class extends Model
    {
        protected $table = 'crud_cov_items';

        protected $fillable = ['name'];
    };

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $insert_request = Request::create('/modify', 'POST', ['name' => 'first']);
    $insert_request->setUserResolver(fn () => $superadmin);
    $insert_data = crud_cov_make_modify_data($item_model, $insert_request, [
        'name' => 'first',
        'not_fillable' => 'x',
    ]);

    $insert_result = $service->insert($insert_data);
    expect($insert_result->statusCode)->toBe(201);
    expect($insert_result->error)->toContain("Discarder 'not_fillable'");

    $inserted_id = $insert_result->data->getKey();

    $update_request = Request::create('/modify', 'POST', ['id' => $inserted_id]);
    $update_request->request->set('id', $inserted_id);
    $update_request->setUserResolver(fn () => $superadmin);
    $update_data = crud_cov_make_modify_data($item_model, $update_request, [
        'name' => 'updated',
        'not_fillable' => 'x',
        'filters' => 'ignored',
    ]);

    $update_result = $service->update($update_data);
    expect($update_result->data)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    expect($update_result->data->first()?->getAttribute('name'))->toBe('updated');
    expect($update_result->error)->toContain("Discarder 'not_fillable'");

    $delete_request = Request::create('/modify', 'POST', ['id' => $inserted_id]);
    $delete_request->request->set('id', $inserted_id);
    $delete_request->setUserResolver(fn () => $superadmin);
    $delete_data = crud_cov_make_modify_data($item_model, $delete_request, []);

    $delete_result = $service->delete($delete_data);
    expect($delete_result->data)->toBe(['deleted' => 1]);
});

it('history and tree throw on unsupported models', function (): void {
    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $plain_model = new class extends Model
    {
        protected $table = 'users';
    };

    $history_request = crud_cov_validated_request();
    $history_request->setUserResolver(fn () => $superadmin);
    $history_data = crud_cov_make_request_data(
        HistoryRequestData::class,
        $plain_model,
        $history_request,
        'id',
        [new Column('users.id', ColumnType::COLUMN)],
    );

    expect(fn () => $service->history($history_data))
        ->toThrow(BadMethodCallException::class);

    $tree_request = crud_cov_validated_request();
    $tree_request->setUserResolver(fn () => $superadmin);
    $tree_data = crud_cov_make_request_data(
        TreeRequestData::class,
        $plain_model,
        $tree_request,
        'id',
        [new Column('users.id', ColumnType::COLUMN)],
    );
    crud_cov_set($tree_data, 'parents', false);
    crud_cov_set($tree_data, 'children', true);

    expect(fn () => $service->tree($tree_data))
        ->toThrow(UnexpectedValueException::class);
});

it('detail resolves single primary key and applies main-entity method columns', function (): void {
    Schema::dropIfExists('crud_cov_d_scalar');
    Schema::create('crud_cov_d_scalar', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->timestamps();
    });

    $scalar_model = new class extends Model
    {
        protected $table = 'crud_cov_d_scalar';

        protected $guarded = [];

        public function rowCode(): string
        {
            return 'X';
        }
    };

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $row = $scalar_model->newQuery()->create(['code' => 'c']);

    $request = crud_cov_validated_request(['id' => $row->getKey()]);
    $request->setUserResolver(fn () => $superadmin);
    $data = crud_cov_make_request_data(
        DetailRequestData::class,
        $scalar_model,
        $request,
        'id',
        [
            new Column('crud_cov_d_scalar.id', ColumnType::COLUMN),
            new Column('crud_cov_d_scalar.rowCode', ColumnType::METHOD),
        ],
    );

    $result = $service->detail($data);

    expect($result->data->getKey())->toBe($row->getKey());
    expect($result->data->getAttribute('rowCode'))->toBe('X');
});

it('detail resolves composite primary key from validated input', function (): void {
    if (! Schema::hasTable('crud_cov_composite_detail')) {
        Schema::create('crud_cov_composite_detail', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id');
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->primary(['tenant_id', 'id']);
        });
    }

    $composite = new class extends Model
    {
        public $incrementing = false;

        protected $table = 'crud_cov_composite_detail';

        protected $guarded = [];

        public function getKeyName(): array
        {
            return ['tenant_id', 'id'];
        }
    };

    $composite->newQuery()->create([
        'tenant_id' => 7,
        'id' => 8,
        'slug' => 'row',
    ]);

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $request = crud_cov_validated_request(['tenant_id' => 7, 'id' => 8]);
    $request->setUserResolver(fn () => $superadmin);
    $data = crud_cov_make_request_data(
        DetailRequestData::class,
        $composite,
        $request,
        ['tenant_id', 'id'],
        [new Column('crud_cov_composite_detail.slug', ColumnType::COLUMN)],
    );

    $result = $service->detail($data);

    expect($result->data)->toBeInstanceOf(Model::class);
    expect($result->data->getAttribute('slug'))->toBe('row');
});

it('history returns record and version history for versionable models', function (): void {
    Schema::dropIfExists('crud_cov_hist_one');
    Schema::create('crud_cov_hist_one', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('label')->nullable();
        $table->timestamps();
    });

    $hist_model = new class extends Model
    {
        use RequiresApproval;
        use Versionable;

        protected $table = 'crud_cov_hist_one';

        protected $guarded = [];

        protected function requiresApprovalWhen($modifications): bool
        {
            return false;
        }
    };

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $target = $hist_model->newQuery()->create(['label' => 'hist-row']);

    $request = crud_cov_validated_request(['id' => $target->getKey()]);
    $request->setUserResolver(fn () => $superadmin);
    $history_data = crud_cov_make_request_data(
        HistoryRequestData::class,
        $target,
        $request,
        'id',
        [new Column('crud_cov_hist_one.id', ColumnType::COLUMN)],
    );

    $limit_prop = new ReflectionProperty($history_data, 'limit');
    $limit_prop->setAccessible(true);
    $limit_prop->setValue($history_data, 5);

    $result = $service->history($history_data);

    expect($result->data)->toBeArray();
    expect($result->data)->toHaveKeys(['record', 'history']);
});

it('tree uses ancestorsAndSelf when only parents are requested', function (): void {
    Schema::dropIfExists('crud_cov_tree_anc');
    Schema::create('crud_cov_tree_anc', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });

    $tree_model = new class extends Model
    {
        use HasRecursiveRelationships;

        protected $table = 'crud_cov_tree_anc';

        protected $guarded = [];
    };

    $solo = $tree_model->newQuery()->create(['name' => 'solo-node', 'parent_id' => null]);

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $request = Request::create('/tree', 'GET', ['id' => $solo->getKey()]);
    $request->query->set('id', $solo->getKey());
    $request->setUserResolver(fn () => $superadmin);
    $data = crud_cov_make_request_data(TreeRequestData::class, $tree_model, $request, 'id', [
        new Column('crud_cov_tree_anc.id', ColumnType::COLUMN),
    ]);
    crud_cov_set($data, 'parents', true);
    crud_cov_set($data, 'children', false);

    $result = $service->tree($data);

    expect($result->data)->toBeInstanceOf(Model::class);
});

it('list uses pagination from-to and count branches', function (): void {
    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));
    $model = new User();

    $base_columns = [new Column('users.id', ColumnType::COLUMN)];

    $req_page = crud_cov_validated_request();
    $req_page->setUserResolver(fn () => $superadmin);
    $data_page = crud_cov_make_request_data(ListRequestData::class, $model, $req_page, 'id', $base_columns);
    crud_cov_set($data_page, 'page', 1);
    crud_cov_set($data_page, 'from', 1);
    crud_cov_set($data_page, 'to', 5);
    crud_cov_set($data_page, 'pagination', 5);
    crud_cov_set($data_page, 'filters', null);
    crud_cov_set($data_page, 'count', false);
    crud_cov_set($data_page, 'group_by', []);
    crud_cov_set($data_page, 'limit', null);
    $page_result = $service->list($data_page);
    expect($page_result->data)->toBeInstanceOf(Illuminate\Support\Collection::class);

    $req_range = crud_cov_validated_request();
    $req_range->setUserResolver(fn () => $superadmin);
    $data_range = crud_cov_make_request_data(ListRequestData::class, $model, $req_range, 'id', $base_columns);
    crud_cov_set($data_range, 'page', null);
    crud_cov_set($data_range, 'from', 1);
    crud_cov_set($data_range, 'to', 3);
    crud_cov_set($data_range, 'pagination', 25);
    crud_cov_set($data_range, 'filters', null);
    crud_cov_set($data_range, 'count', false);
    crud_cov_set($data_range, 'group_by', []);
    crud_cov_set($data_range, 'limit', null);
    $range_result = $service->list($data_range);
    expect($range_result->data)->toBeInstanceOf(Illuminate\Support\Collection::class);

    $req_count = crud_cov_validated_request();
    $req_count->setUserResolver(fn () => $superadmin);
    $data_count = crud_cov_make_request_data(ListRequestData::class, $model, $req_count, 'id', $base_columns);
    crud_cov_set($data_count, 'page', null);
    crud_cov_set($data_count, 'from', null);
    crud_cov_set($data_count, 'to', null);
    crud_cov_set($data_count, 'pagination', 25);
    crud_cov_set($data_count, 'filters', null);
    crud_cov_set($data_count, 'count', true);
    crud_cov_set($data_count, 'group_by', []);
    crud_cov_set($data_count, 'limit', null);
    $count_result = $service->list($data_count);
    expect($count_result->data)->toBeInt();
});

it('approve and disapprove run modification workflows', function (): void {
    if (! Schema::hasTable('crud_cov_approval')) {
        Schema::create('crud_cov_approval', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    } elseif (! Schema::hasColumn('crud_cov_approval', 'deleted_at')) {
        Schema::table('crud_cov_approval', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->softDeletes();
        });
    }

    $approval_model = new class extends Model
    {
        use RequiresApproval;
        use SoftDeletes;

        protected $table = 'crud_cov_approval';

        protected $guarded = [];

        protected function requiresApprovalWhen($modifications): bool
        {
            return false;
        }
    };

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $row = $approval_model->newQuery()->create(['title' => 'pending']);

    $mod = Modification::query()->create([
        'modifiable_id' => $row->getKey(),
        'modifiable_type' => $approval_model::class,
        'modifier_id' => $superadmin->getKey(),
        'modifier_type' => User::class,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 2,
        'disapprovers_required' => 2,
        'md5' => md5(json_encode(['title' => 'next'])),
        'modifications' => ['title' => 'next'],
    ]);

    $req = Request::create('/approve', 'POST');
    $req->setUserResolver(fn () => $superadmin);
    $modify_loop = crud_cov_make_modify_data($row, $req, ['id' => $row->getKey()], $row->getKey());
    $loop_result = $service->approve($modify_loop);
    expect($loop_result->data)->toBeInstanceOf(Model::class);

    $modify_single = crud_cov_make_modify_data($row, $req, [
        'id' => $row->getKey(),
        'modification' => $mod->getKey(),
    ], $row->getKey());
    $single_result = $service->approve($modify_single);
    expect($single_result->data)->toBeInstanceOf(Model::class);

    $disapprove_result = $service->disapprove($modify_single);
    expect($disapprove_result->data)->toBeInstanceOf(Model::class);
});

it('disapprove iterates active modifications when no modification id is passed', function (): void {
    if (! Schema::hasTable('crud_cov_approval')) {
        Schema::create('crud_cov_approval', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    } elseif (! Schema::hasColumn('crud_cov_approval', 'deleted_at')) {
        Schema::table('crud_cov_approval', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->softDeletes();
        });
    }

    $approval_model = new class extends Model
    {
        use RequiresApproval;
        use SoftDeletes;

        protected $table = 'crud_cov_approval';

        protected $guarded = [];

        protected function requiresApprovalWhen($modifications): bool
        {
            return false;
        }
    };

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $row = $approval_model->newQuery()->create(['title' => 'dis-cursor']);

    Modification::query()->create([
        'modifiable_id' => $row->getKey(),
        'modifiable_type' => $approval_model::class,
        'modifier_id' => $superadmin->getKey(),
        'modifier_type' => User::class,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 2,
        'disapprovers_required' => 2,
        'md5' => md5(json_encode(['title' => 'a'])),
        'modifications' => ['title' => 'a'],
    ]);

    $req = Request::create('/disapprove', 'POST');
    $req->setUserResolver(fn () => $superadmin);
    $modify = crud_cov_make_modify_data($row, $req, ['id' => $row->getKey()], $row->getKey());

    $result = $service->disapprove($modify);

    expect($result->data)->toBeInstanceOf(Model::class);
});

it('approve throws when no active modifications exist', function (): void {
    if (! Schema::hasTable('crud_cov_approval')) {
        Schema::create('crud_cov_approval', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    } elseif (! Schema::hasColumn('crud_cov_approval', 'deleted_at')) {
        Schema::table('crud_cov_approval', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->softDeletes();
        });
    }

    $approval_model = new class extends Model
    {
        use RequiresApproval;
        use SoftDeletes;

        protected $table = 'crud_cov_approval';

        protected $guarded = [];

        protected function requiresApprovalWhen($modifications): bool
        {
            return false;
        }
    };

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));
    $row = $approval_model->newQuery()->create(['title' => 'empty-mods']);

    $req = Request::create('/approve', 'POST');
    $req->setUserResolver(fn () => $superadmin);
    $modify = crud_cov_make_modify_data($row, $req, ['id' => $row->getKey()], $row->getKey());

    expect(fn () => $service->approve($modify))->toThrow(LogicException::class);
});

it('lock unlock and guard branches on lockable models', function (): void {
    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $target = User::factory()->create([
        'username' => 'lock_' . uniqid(),
        'email' => 'lock_' . uniqid() . '@example.com',
    ]);

    $req_no_query_id = Request::create('/lock', 'POST');
    $req_no_query_id->setUserResolver(fn () => $superadmin);
    $modify_unlocked = crud_cov_make_modify_data($target, $req_no_query_id, ['id' => $target->getKey()]);
    $lock_result = $service->lock($modify_unlocked);
    expect($lock_result->data)->not->toBeEmpty();

    $req_with_id = Request::create('/lock', 'POST', ['id' => $target->getKey()]);
    $req_with_id->request->set('id', $target->getKey());
    $req_with_id->setUserResolver(fn () => $superadmin);
    $modify_locked = crud_cov_make_modify_data($target, $req_with_id, ['id' => $target->getKey()]);

    expect(fn () => $service->lock($modify_locked))->toThrow(AlreadyLockedException::class);

    $service->unlock($modify_unlocked);

    $req_missing = Request::create('/lock', 'POST', ['id' => 999_999_999]);
    $req_missing->request->set('id', 999_999_999);
    $req_missing->setUserResolver(fn () => $superadmin);
    $modify_missing = crud_cov_make_modify_data($target, $req_missing, ['id' => 999_999_999]);

    expect(fn () => $service->lock($modify_missing))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class, 'No model Found');
});

it('lock throws when model does not support locks', function (): void {
    if (! Schema::hasTable('crud_cov_items')) {
        Schema::create('crud_cov_items', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    $plain = new class extends Model
    {
        protected $table = 'crud_cov_items';

        protected $guarded = [];
    };

    $superadmin = crud_cov_login_superadmin();
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $row = $plain->newQuery()->create(['name' => 'n']);

    $req = Request::create('/lock', 'POST');
    $req->setUserResolver(fn () => $superadmin);
    $modify = crud_cov_make_modify_data($row, $req, ['id' => $row->getKey()]);

    expect(fn () => $service->lock($modify))->toThrow(BadMethodCallException::class);
});
