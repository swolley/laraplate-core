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
        ->toBe(PermissionName::build($user->getConnectionName() ?? 'default', $user->getTable(), 'select'));
});

it('builds a name from a class string without instantiating the model', function (): void {
    expect(PermissionName::forClass(User::class, 'delete'))
        ->toBe(PermissionName::forModel(new User(), 'delete'));
});
