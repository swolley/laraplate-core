<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Stubs\Crud\AggregateCustomerStubModel;
use Modules\Core\Tests\Stubs\Crud\AggregateCustomerTranslationStubModel;
use Modules\Core\Tests\Stubs\Crud\AggregateOrderStubModel;
use ReflectionClass;
use ReflectionProperty;

/**
 * What every aggregate test needs: the stub tables, a superadmin or a reader under an ACL to call the CRUD
 * service as, a list request built without the HTTP layer, and rows to measure.
 *
 * The tables are `aggregate_customers`, `aggregate_customer_translations` and `aggregate_orders`, read through
 * {@see AggregateCustomerStubModel}, {@see AggregateCustomerTranslationStubModel} and {@see AggregateOrderStubModel}.
 */
final class AggregateTestKit
{
    private const string DEFAULT_CUSTOMER = 'Default customer';

    /**
     * Create the stub tables for the running test. The schema inspector keeps what it read of a table for the
     * whole process, so what it read in an earlier test is dropped.
     */
    public static function createTables(): void
    {
        Schema::create('aggregate_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('country');
        });

        Schema::create('aggregate_customer_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('aggregate_customers');
            $table->string('locale', 5);
            $table->string('name');
        });

        Schema::create('aggregate_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('aggregate_customers');
            $table->string('status');
            $table->decimal('total', 15, 4)->nullable();
            $table->integer('quantity');
            $table->char('currency', 3)->nullable();
            $table->dateTime('ordered_at');
            $table->boolean('is_paid');
        });

        SchemaInspector::getInstance()->clearTable('aggregate_customers');
        SchemaInspector::getInstance()->clearTable('aggregate_customer_translations');
        SchemaInspector::getInstance()->clearTable('aggregate_orders');
    }

    /**
     * Create a user with the superadmin role and log it in.
     */
    public static function asSuperadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate((string) config('permission.roles.superadmin'), 'web'));
        auth()->login($user);

        return $user;
    }

    /**
     * A list request over the model, as the CRUD service receives it, whose user is whoever is logged in when
     * the service asks.
     */
    public static function listData(Model $model, ?FiltersGroup $filters = null): ListRequestData
    {
        $request = new Request;
        $request->setUserResolver(static fn (): ?Authenticatable => auth()->user());

        $data = new ReflectionClass(ListRequestData::class)->newInstanceWithoutConstructor();

        foreach ([
            'request' => $request,
            'mainEntity' => $model->getTable(),
            'primaryKey' => $model->getKeyName(),
            'module' => null,
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
        ] as $property => $value) {
            new ReflectionProperty($data, $property)->setValue($data, $value);
        }

        return $data;
    }

    /**
     * Create a user whose role may select each given model, narrowed by the row-level ACL given for it, if any,
     * and log it in.
     *
     * @param  array<class-string<Model>, FiltersGroup|null>  $grants
     */
    public static function asReader(array $grants): User
    {
        $role = Role::factory()->create(['name' => 'aggregate_reader_' . uniqid(), 'guard_name' => 'web']);

        foreach ($grants as $model_class => $filters) {
            $permission = Permission::findOrCreate(PermissionName::forModel(new $model_class, 'select'), 'web');
            $role->givePermissionTo($permission);

            if ($filters instanceof FiltersGroup) {
                $acl = new ACL;
                $acl->setSkipValidation(true);
                $acl->forceFill([
                    'permission_id' => $permission->id,
                    'role_id' => $role->id,
                    'filters' => $filters,
                    'unrestricted' => false,
                    'priority' => 10,
                    'is_active' => true,
                ]);
                $acl->save();
            }
        }

        $reader = User::factory()->create();
        $reader->assignRole($role);
        auth()->login($reader);

        return $reader;
    }

    /**
     * Insert orders. A row names only the columns that matter to its test: `customer_id` defaults to a shared
     * customer (a row that names it as null has no customer), the others to an unpaid order of one unit, with
     * no amount and no currency.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function seedOrders(array $rows): void
    {
        foreach ($rows as $row) {
            if (! array_key_exists('customer_id', $row)) {
                $row['customer_id'] = self::defaultCustomerId();
            }

            AggregateOrderStubModel::query()->create($row + [
                'status' => 'new',
                'total' => null,
                'quantity' => 1,
                'currency' => null,
                'ordered_at' => '2026-01-15 12:00:00',
                'is_paid' => false,
            ]);
        }
    }

    private static function defaultCustomerId(): int
    {
        $customer = AggregateCustomerStubModel::query()->firstOrCreate(
            ['name' => self::DEFAULT_CUSTOMER],
            ['country' => 'IT'],
        );

        return $customer->id;
    }
}
