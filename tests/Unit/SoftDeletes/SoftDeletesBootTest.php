<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\ModuleDatabaseActivator;
use Modules\Core\Overrides\CustomSoftDeletingScope;
use Modules\Core\Tests\Stubs\SoftDeletesStubModel;

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
