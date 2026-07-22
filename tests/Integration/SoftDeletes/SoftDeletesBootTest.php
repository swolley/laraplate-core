<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\ModuleDatabaseActivator;
use Modules\Core\Overrides\CustomSoftDeletingScope;
use Modules\Core\Tests\Stubs\SoftDeletesStubModel;

class ModuleDatabaseActivatorAffinityModel extends Model
{
    protected $connection = 'module_activator_affinity';

    protected $table = 'custom_module_settings';
}

it('boots soft deletes trait without a database connection resolver', function (): void {
    Model::clearBootedModels();
    Model::unsetConnectionResolver();

    new SoftDeletesStubModel;

    expect(SoftDeletesStubModel::hasGlobalScope(CustomSoftDeletingScope::class))->toBeTrue();
});

it('returns false from checkSettingTable when the database is not bound', function (): void {
    $app = app();
    $had_db = $app->bound('db');
    $db = $had_db ? $app->make('db') : null;

    if ($had_db) {
        $app->offsetUnset('db');
    }

    try {
        expect(ModuleDatabaseActivator::checkSettingTable())->toBeFalse();
    } finally {
        if ($had_db && $db !== null) {
            $app->instance('db', $db);
        }
    }
});

it('checks the configured model table and connection without the Eloquent resolver', function (): void {
    $manager = app('db');
    $resolver = Model::getConnectionResolver();
    $original_model = ModuleDatabaseActivator::$MODEL_NAME;

    config()->set('database.connections.module_activator_affinity', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    $manager->purge('module_activator_affinity');
    $manager->connection('module_activator_affinity')->getSchemaBuilder()->create(
        'custom_module_settings',
        fn (Blueprint $table) => $table->id(),
    );

    Cache::forget('modules_db_activator_checked');
    ModuleDatabaseActivator::$MODEL_NAME = ModuleDatabaseActivatorAffinityModel::class;
    Model::unsetConnectionResolver();

    try {
        expect(ModuleDatabaseActivator::checkSettingTable())->toBeTrue();
    } finally {
        ModuleDatabaseActivator::$MODEL_NAME = $original_model;
        Cache::forget('modules_db_activator_checked');

        if ($resolver !== null) {
            Model::setConnectionResolver($resolver);
        }

        $manager->purge('module_activator_affinity');
    }
});
