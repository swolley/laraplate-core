<?php

declare(strict_types=1);

use Modules\Core\Models\ACL;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('getRules merges acl validation rules with trait defaults', function (): void {
    $rules = (new ACL)->getRules();

    expect($rules['always'])->toHaveKeys([
        'permission_id',
        'filters',
        'description',
        'unrestricted',
        'priority',
        'is_active',
    ])
        ->and($rules['always']['permission_id'])->toContain('required')
        ->and($rules['always']['sort.*.property'] ?? null)->toContain('string')
        ->and($rules['always']['sort.*.direction'] ?? null)->toContain('in:asc,desc,ASC,DESC');
});

it('forPermission scope filters by permission id', function (): void {
    $sql = ACL::query()->forPermission(42)->toSql();

    expect($sql)->toContain('permission_id');
});

it('active scope filters enabled rows', function (): void {
    $sql = ACL::query()->active()->toSql();

    expect($sql)->toContain('is_active');
});

it('byPriority scope orders by priority descending', function (): void {
    $sql = ACL::query()->byPriority()->toSql();

    expect($sql)->toContain('priority');
});
