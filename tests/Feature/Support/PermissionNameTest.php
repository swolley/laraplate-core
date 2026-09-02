<?php

declare(strict_types=1);

use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;

it('builds a name from an explicit connection, table and operation', function (): void {
    expect(PermissionName::build('tenant_a', 'erp_invoices', 'update'))
        ->toBe('tenant_a.erp_invoices.update');
});

it('falls back to the default connection for a model without one', function (): void {
    $user = new User();

    expect(PermissionName::forModel($user, 'select'))
        ->toBe('default.' . $user->getTable() . '.select');
});

it('names the resolved default connection `default`', function (): void {
    // Eloquent stamps the resolved connection onto every hydrated record, so a model
    // read back from the database reports `mysql`/`sqlite` where a fresh instance
    // reports null. Both must land on the same permission.
    $user = (new User())->setConnection((string) config('database.default'));

    expect(PermissionName::forModel($user, 'select'))
        ->toBe('default.' . $user->getTable() . '.select')
        ->and(PermissionName::build((string) config('database.default'), 'erp_invoices', 'post'))
        ->toBe('default.erp_invoices.post');
});

it('keeps a secondary connection in the name', function (): void {
    $user = (new User())->setConnection('tenant_a');

    expect(PermissionName::forModel($user, 'select'))
        ->toBe('tenant_a.' . $user->getTable() . '.select');
});

it('builds a name from a class string without instantiating the model', function (): void {
    expect(PermissionName::forClass(User::class, 'delete'))
        ->toBe(PermissionName::forModel(new User(), 'delete'));
});
