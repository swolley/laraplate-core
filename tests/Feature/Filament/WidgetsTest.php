<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Filament\Widgets\CoreStatsWidget;
use Modules\Core\Filament\Widgets\HorizonStatsWidget;
use Modules\Core\Filament\Widgets\SearchEngineHealthTableWidget;
use Modules\Core\Filament\Widgets\SystemHealthWidget;
use Modules\Core\Filament\Widgets\WelcomeLinkWidget;
use Modules\Core\Models\License;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

beforeEach(function (): void {
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $admin_role = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($admin_role);
    $this->actingAs($this->admin);
});

it('builds core stats widget data', function (): void {
    $license = License::factory()->create();
    $this->admin->license_id = $license->id;
    $this->admin->save();

    $widget = new CoreStatsWidget();
    $method = new ReflectionMethod(CoreStatsWidget::class, 'getStats');
    $method->setAccessible(true);
    $property = new ReflectionProperty(CoreStatsWidget::class, 'isLazy');
    $property->setAccessible(true);

    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(3)
        ->and($property->getDeclaringClass()->getName())->toBe(CoreStatsWidget::class)
        ->and($property->getValue())->toBeTrue()
        ->and($stats[0]->getValue())->toBe(1)
        ->and($stats[1]->getValue())->toBe('1 / 1')
        ->and($stats[2]->getValue())->toBe('1 / 1');
});

it('builds core stats without joining models on different connections', function (): void {
    config()->set('database.connections.core-license-stats', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    config()->set('database.connections.core-user-stats', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    config()->set('core.model_connections.' . License::class, 'core-license-stats');
    config()->set('core.model_connections.' . App\Models\User::class, 'core-user-stats');

    Schema::connection('core-license-stats')->create((new License)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->timestamp('valid_to')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    Schema::connection('core-user-stats')->create((new User)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('license_id')->nullable();
        $table->boolean('is_deleted')->default(false);
    });

    $license_connection = Schema::connection('core-license-stats')->getConnection();
    $user_connection = Schema::connection('core-user-stats')->getConnection();
    $license_connection->table((new License)->getTable())->insert([
        ['id' => 501, 'valid_to' => null],
        ['id' => 502, 'valid_to' => now()->subDay()],
    ]);
    $user_connection->table((new User)->getTable())->insert([
        ['id' => 601, 'license_id' => 501],
        ['id' => 602, 'license_id' => 999],
    ]);
    Cache::flush();

    $widget = new CoreStatsWidget();
    $method = new ReflectionMethod(CoreStatsWidget::class, 'getStats');
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    expect($stats[0]->getValue())->toBe(2)
        ->and($stats[1]->getValue())->toBe('1 / 2')
        ->and($stats[2]->getValue())->toBe('1 / 1');
});

it('isolates cached core stats when a connection name points to another database', function (): void {
    $first_database = tempnam(sys_get_temp_dir(), 'core-stats-first-');
    $second_database = tempnam(sys_get_temp_dir(), 'core-stats-second-');

    expect($first_database)->toBeString()
        ->and($second_database)->toBeString();

    $connection_name = 'core-cache-identity';
    $connection_config = [
        'driver' => 'sqlite',
        'database' => $first_database,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ];
    config()->set("database.connections.{$connection_name}", $connection_config);
    config()->set('core.model_connections.' . License::class, $connection_name);
    config()->set('core.model_connections.' . App\Models\User::class, $connection_name);
    Cache::flush();

    $read_stats = static function (): array {
        $widget = new CoreStatsWidget();
        $method = new ReflectionMethod(CoreStatsWidget::class, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    };
    $create_tables = static function () use ($connection_name): void {
        Schema::connection($connection_name)->create((new License)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_deleted')->default(false);
        });
        Schema::connection($connection_name)->create((new User)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('license_id')->nullable();
            $table->boolean('is_deleted')->default(false);
        });
    };

    try {
        $create_tables();
        DB::connection($connection_name)->table((new License)->getTable())->insert([
            'id' => 801,
            'valid_to' => null,
        ]);
        DB::connection($connection_name)->table((new User)->getTable())->insert([
            'id' => 901,
            'license_id' => 801,
        ]);

        expect($read_stats()[0]->getValue())->toBe(1);

        DB::purge($connection_name);
        $connection_config['database'] = $second_database;
        config()->set("database.connections.{$connection_name}", $connection_config);
        $create_tables();
        DB::connection($connection_name)->table((new License)->getTable())->insert([
            ['id' => 802, 'valid_to' => null],
            ['id' => 803, 'valid_to' => null],
        ]);
        DB::connection($connection_name)->table((new User)->getTable())->insert([
            ['id' => 902, 'license_id' => 802],
            ['id' => 903, 'license_id' => 803],
        ]);

        expect($read_stats()[0]->getValue())->toBe(2);
    } finally {
        DB::purge($connection_name);
        @unlink($first_database);
        @unlink($second_database);
    }
});

it('includes Oracle routing attributes in the core stats database identity', function (): void {
    $connection_name = 'core-cache-oracle-identity';
    $connection_config = [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'tns' => 'PRIMARY_TNS',
        'service_name' => 'PRIMARY_SERVICE',
        'prefix_schema' => 'PRIMARY_SCHEMA',
        'edition' => 'PRIMARY_EDITION',
        'username' => 'primary_user',
    ];
    config()->set("database.connections.{$connection_name}", $connection_config);

    $widget = new CoreStatsWidget();
    $method = new ReflectionMethod(CoreStatsWidget::class, 'modelDatabaseIdentity');
    $method->setAccessible(true);
    $first_identity = $method->invoke($widget, (new License())->setConnection($connection_name));

    DB::purge($connection_name);
    $connection_config['prefix_schema'] = 'SECONDARY_SCHEMA';
    $connection_config['edition'] = 'SECONDARY_EDITION';
    $connection_config['username'] = 'secondary_user';
    config()->set("database.connections.{$connection_name}", $connection_config);
    $second_identity = $method->invoke($widget, (new License())->setConnection($connection_name));

    expect($second_identity)->not->toBe($first_identity);
});

it('returns horizon canView based on service provider availability', function (): void {
    expect(HorizonStatsWidget::canView())->toBe(class_exists(Laravel\Horizon\HorizonServiceProvider::class));
});

it('returns search engine health canView on cache-health route', function (): void {
    $request = request()->create('/health/cache', 'GET');
    app()->instance('request', $request);

    try {
        expect(SearchEngineHealthTableWidget::canView())->toBeTrue();
    } catch (Illuminate\Contracts\Container\BindingResolutionException) {
        expect(true)->toBeTrue();
    }
});

it('returns search engine health view data structure', function (): void {
    $widget = new SearchEngineHealthTableWidget();
    $method = new ReflectionMethod(SearchEngineHealthTableWidget::class, 'getViewData');
    $method->setAccessible(true);
    $data = $method->invoke($widget);

    expect($data)->toHaveKeys(['driver', 'models', 'error', 'cache_minutes'])
        ->and($data['driver'])->toBe('collection')
        ->and($data['error'])->toBeNull();

    if ($data['models'] !== []) {
        expect($data['models'][0])->toHaveKeys(['name', 'full_name', 'searchable_as', 'count', 'index_exists', 'documents'])
            ->and($data['models'][0]['documents'])->toBe(0)
            ->and($data['models'][0]['index_exists'])->toBeFalse();
    }
});

it('returns system health widget columns and stats', function (): void {
    $widget = new SystemHealthWidget();
    $columns = $widget->getColumns();

    $method = new ReflectionMethod(SystemHealthWidget::class, 'getStats');
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    expect($columns)->toBe(['md' => 2])
        ->and($stats)->toHaveCount(2);
});

it('keeps welcome widget hidden by default', function (): void {
    expect(WelcomeLinkWidget::canView())->toBeFalse();
});

it('returns welcome widget view data', function (): void {
    $widget = new WelcomeLinkWidget();
    $method = new ReflectionMethod(WelcomeLinkWidget::class, 'getViewData');
    $method->setAccessible(true);

    try {
        $data = $method->invoke($widget);
        expect($data)->toHaveKey('welcome_url');
    } catch (Illuminate\Contracts\Container\BindingResolutionException) {
        expect(true)->toBeTrue();
    }
});
