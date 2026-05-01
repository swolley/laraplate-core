<?php

declare(strict_types=1);

use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use App\Models\User;
use Modules\Core\Services\Crud\QueryBuilder;


it('applies equals null as whereNull', function (): void {
    $user_with_null = User::factory()->create(['email_verified_at' => null]);
    User::factory()->create(['email_verified_at' => now()]);

    $query = User::query();
    $filters = new FiltersGroup([
        new Filter('email_verified_at', null, FilterOperator::EQUALS),
    ]);

    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_with_null->id]);
});

it('applies not equals null as whereNotNull', function (): void {
    User::factory()->create(['email_verified_at' => null]);
    $user_with_verified_email = User::factory()->create(['email_verified_at' => now()]);

    $query = User::query();
    $filters = new FiltersGroup([
        new Filter('email_verified_at', null, FilterOperator::NOT_EQUALS),
    ]);

    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_with_verified_email->id]);
});

it('applies IN operator with scalar value', function (): void {
    $user_a = User::factory()->create(['username' => 'alpha']);
    User::factory()->create(['username' => 'beta']);

    $query = User::query();
    $filters = new FiltersGroup([
        new Filter('username', 'alpha', FilterOperator::IN),
    ]);

    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_a->id]);
});

it('applies BETWEEN operator', function (): void {
    $u1 = User::factory()->create(['username' => 'u1']);
    $u2 = User::factory()->create(['username' => 'u2']);
    $u3 = User::factory()->create(['username' => 'u3']);

    $query = User::query();
    $filters = new FiltersGroup([
        new Filter('id', [$u1->id, $u2->id], FilterOperator::BETWEEN),
    ]);

    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$u1->id, $u2->id]);
    expect($query->pluck('id')->all())->not->toContain($u3->id);
});

it('applies LIKE and NOT LIKE operators', function (): void {
    $user_jon = User::factory()->create(['username' => 'jon.doe']);
    $user_jane = User::factory()->create(['username' => 'jane.doe']);
    $user_admin = User::factory()->create(['username' => 'admin']);

    $query_like = User::query();
    $filters_like = new FiltersGroup([
        new Filter('username', '%doe%', FilterOperator::LIKE),
    ]);
    (new QueryBuilder())->applyFilters($query_like, $filters_like);

    expect($query_like->pluck('id')->all())->toEqualCanonicalizing([$user_jon->id, $user_jane->id]);

    $query_not_like = User::query();
    $filters_not_like = new FiltersGroup([
        new Filter('username', '%doe%', FilterOperator::NOT_LIKE),
    ]);
    (new QueryBuilder())->applyFilters($query_not_like, $filters_not_like);

    expect($query_not_like->pluck('id')->all())->toContain($user_admin->id);
    expect($query_not_like->pluck('id')->all())->not->toContain($user_jon->id);
});

it('supports OR groups with nested AND group', function (): void {
    $user_alpha = User::factory()->create(['username' => 'alpha', 'email' => 'alpha@example.com']);
    $user_beta = User::factory()->create(['username' => 'beta', 'email' => 'beta@example.com']);
    User::factory()->create(['username' => 'gamma', 'email' => 'gamma@example.com']);

    $filters = new FiltersGroup([
        new Filter('username', 'alpha', FilterOperator::EQUALS),
        new FiltersGroup([
            new Filter('username', 'beta', FilterOperator::EQUALS),
            new Filter('email', 'beta@example.com', FilterOperator::EQUALS),
        ], WhereClause::AND),
    ], WhereClause::OR);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$user_alpha->id, $user_beta->id]);
});

it('applies relation field filters using whereHas', function (): void {
    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_support = Role::factory()->create(['name' => 'support', 'guard_name' => 'web']);

    $user_sales = User::factory()->create();
    $user_sales->assignRole($role_sales);

    $user_support = User::factory()->create();
    $user_support->assignRole($role_support);

    $filters = new FiltersGroup([
        new Filter('roles.name', 'sales', FilterOperator::EQUALS),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_sales->id]);
    expect($query->pluck('id')->all())->not->toContain($user_support->id);
});

it('supports has() via property pointing to a relation method', function (): void {
    $role = Role::factory()->create(['guard_name' => 'web']);
    $user_with_role = User::factory()->create();
    $user_with_role->assignRole($role);

    $user_without_role = User::factory()->create();

    $filters = new FiltersGroup([
        new Filter('users.roles', 0, FilterOperator::GREAT),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_with_role->id]);
    expect($query->pluck('id')->all())->not->toContain($user_without_role->id);
});

it('applies nested relation filter on roles.permissions.name', function (): void {
    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_support = Role::factory()->create(['name' => 'support', 'guard_name' => 'web']);

    $permission_orders = Permission::factory()->create(['name' => 'default.orders.select', 'guard_name' => 'web']);
    $permission_users = Permission::factory()->create(['name' => 'default.users.select', 'guard_name' => 'web']);

    $role_sales->givePermissionTo($permission_orders);
    $role_support->givePermissionTo($permission_users);

    $user_sales = User::factory()->create();
    $user_sales->assignRole($role_sales);

    $user_support = User::factory()->create();
    $user_support->assignRole($role_support);

    $filters = new FiltersGroup([
        new Filter('roles.permissions.name', 'default.orders.select', FilterOperator::EQUALS),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_sales->id]);
    expect($query->pluck('id')->all())->not->toContain($user_support->id);
});

it('supports IN operator on nested relation fields', function (): void {
    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_admin = Role::factory()->create(['name' => 'admin', 'guard_name' => 'web']);
    $role_other = Role::factory()->create(['name' => 'other', 'guard_name' => 'web']);

    $u1 = User::factory()->create();
    $u1->assignRole($role_sales);

    $u2 = User::factory()->create();
    $u2->assignRole($role_admin);

    $u3 = User::factory()->create();
    $u3->assignRole($role_other);

    $filters = new FiltersGroup([
        new Filter('roles.name', ['sales', 'admin'], FilterOperator::IN),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$u1->id, $u2->id]);
    expect($query->pluck('id')->all())->not->toContain($u3->id);
});

it('applies mixed OR/AND with nested relation and root filters', function (): void {
    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_support = Role::factory()->create(['name' => 'support', 'guard_name' => 'web']);

    $u1 = User::factory()->create(['username' => 'alpha']);
    $u1->assignRole($role_sales);

    $u2 = User::factory()->create(['username' => 'beta']);
    $u2->assignRole($role_support);

    $u3 = User::factory()->create(['username' => 'gamma']);
    $u3->assignRole($role_sales);

    $filters = new FiltersGroup([
        new FiltersGroup([
            new Filter('roles.name', 'sales', FilterOperator::EQUALS),
            new Filter('username', 'alpha', FilterOperator::EQUALS),
        ], WhereClause::AND),
        new FiltersGroup([
            new Filter('roles.name', 'support', FilterOperator::EQUALS),
            new Filter('username', 'beta', FilterOperator::EQUALS),
        ], WhereClause::AND),
    ], WhereClause::OR);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$u1->id, $u2->id]);
    expect($query->pluck('id')->all())->not->toContain($u3->id);
});

it('applies LIKE on nested relation field roles.permissions.name', function (): void {
    $role_a = Role::factory()->create(['name' => 'team-a', 'guard_name' => 'web']);
    $role_b = Role::factory()->create(['name' => 'team-b', 'guard_name' => 'web']);

    $perm_orders = Permission::factory()->create(['name' => 'default.orders.select', 'guard_name' => 'web']);
    $perm_users = Permission::factory()->create(['name' => 'default.users.select', 'guard_name' => 'web']);

    $role_a->givePermissionTo($perm_orders);
    $role_b->givePermissionTo($perm_users);

    $user_match = User::factory()->create();
    $user_match->assignRole($role_a);

    $user_no_match = User::factory()->create();
    $user_no_match->assignRole($role_b);

    $filters = new FiltersGroup([
        new Filter('roles.permissions.name', '%orders%', FilterOperator::LIKE),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_match->id]);
    expect($query->pluck('id')->all())->not->toContain($user_no_match->id);
});

it('applies BETWEEN on relation field roles.id', function (): void {
    $role_low = Role::factory()->create(['name' => 'low_role', 'guard_name' => 'web']);
    $role_mid = Role::factory()->create(['name' => 'mid_role', 'guard_name' => 'web']);
    $role_high = Role::factory()->create(['name' => 'high_role', 'guard_name' => 'web']);

    $low_id = min($role_low->id, $role_mid->id);
    $high_id = max($role_low->id, $role_mid->id);

    $u_in_range = User::factory()->create();
    $u_in_range->assignRole($role_low);

    $u_in_range_b = User::factory()->create();
    $u_in_range_b->assignRole($role_mid);

    $u_out = User::factory()->create();
    $u_out->assignRole($role_high);

    $filters = new FiltersGroup([
        new Filter('roles.id', [$low_id, $high_id], FilterOperator::BETWEEN),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$u_in_range->id, $u_in_range_b->id]);
    expect($query->pluck('id')->all())->not->toContain($u_out->id);
});

it('applies three-level OR nesting with relation and root predicates', function (): void {
    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_support = Role::factory()->create(['name' => 'support', 'guard_name' => 'web']);
    $role_admin = Role::factory()->create(['name' => 'admin', 'guard_name' => 'web']);

    $u_branch_a = User::factory()->create(['username' => 'alpha']);
    $u_branch_a->assignRole($role_sales);

    $u_branch_b = User::factory()->create(['username' => 'beta']);
    $u_branch_b->assignRole($role_support);

    $u_branch_c = User::factory()->create(['username' => 'delta']);
    $u_branch_c->assignRole($role_admin);

    $u_false_sales_wrong_user = User::factory()->create(['username' => 'gamma']);
    $u_false_sales_wrong_user->assignRole($role_sales);

    $filters = new FiltersGroup([
        new FiltersGroup([
            new FiltersGroup([
                new Filter('roles.name', 'sales', FilterOperator::EQUALS),
                new Filter('username', 'alpha', FilterOperator::EQUALS),
            ], WhereClause::AND),
            new FiltersGroup([
                new Filter('roles.name', 'support', FilterOperator::EQUALS),
                new Filter('username', 'beta', FilterOperator::EQUALS),
            ], WhereClause::AND),
        ], WhereClause::OR),
        new Filter('username', 'delta', FilterOperator::EQUALS),
    ], WhereClause::OR);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$u_branch_a->id, $u_branch_b->id, $u_branch_c->id]);
    expect($query->pluck('id')->all())->not->toContain($u_false_sales_wrong_user->id);
});

it('applies NOT LIKE on nested relation field roles.permissions.name', function (): void {
    $role_orders = Role::factory()->create(['name' => 'role-orders', 'guard_name' => 'web']);
    $role_reports = Role::factory()->create(['name' => 'role-reports', 'guard_name' => 'web']);

    $perm_orders = Permission::factory()->create(['name' => 'default.orders.select', 'guard_name' => 'web']);
    $perm_reports = Permission::factory()->create(['name' => 'default.reports.select', 'guard_name' => 'web']);

    $role_orders->givePermissionTo($perm_orders);
    $role_reports->givePermissionTo($perm_reports);

    $user_orders_only = User::factory()->create();
    $user_orders_only->assignRole($role_orders);

    $user_reports = User::factory()->create();
    $user_reports->assignRole($role_reports);

    $filters = new FiltersGroup([
        new Filter('roles.permissions.name', '%orders%', FilterOperator::NOT_LIKE),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_reports->id]);
    expect($query->pluck('id')->all())->not->toContain($user_orders_only->id);
});

it('applies BETWEEN on nested relation field roles.permissions.id', function (): void {
    $role_in = Role::factory()->create(['name' => 'role-in-range', 'guard_name' => 'web']);
    $role_out = Role::factory()->create(['name' => 'role-out-range', 'guard_name' => 'web']);

    $perm_a = Permission::factory()->create(['name' => 'default.qb_between_a.select', 'guard_name' => 'web']);
    $perm_b = Permission::factory()->create(['name' => 'default.qb_between_b.select', 'guard_name' => 'web']);
    $perm_far = Permission::factory()->create(['name' => 'default.qb_between_far.select', 'guard_name' => 'web']);

    $role_in->givePermissionTo([$perm_a, $perm_b]);
    $role_out->givePermissionTo($perm_far);

    $low_id = min($perm_a->id, $perm_b->id);
    $high_id = max($perm_a->id, $perm_b->id);

    $user_in = User::factory()->create();
    $user_in->assignRole($role_in);

    $user_out = User::factory()->create();
    $user_out->assignRole($role_out);

    $filters = new FiltersGroup([
        new Filter('roles.permissions.id', [$low_id, $high_id], FilterOperator::BETWEEN),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_in->id]);
    expect($query->pluck('id')->all())->not->toContain($user_out->id);
});

it('applies greater than on main model column', function (): void {
    $u_low = User::factory()->create();
    $u_mid = User::factory()->create();
    $u_high = User::factory()->create();

    $mid_id = $u_mid->id;

    $filters = new FiltersGroup([
        new Filter('id', $mid_id, FilterOperator::GREAT),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toContain($u_high->id);
    expect($query->pluck('id')->all())->not->toContain($u_low->id);
    expect($query->pluck('id')->all())->not->toContain($u_mid->id);
});

it('applies great equals on relation field roles.id', function (): void {
    $role_a = Role::factory()->create(['name' => 'qb_ge_a', 'guard_name' => 'web']);
    $role_b = Role::factory()->create(['name' => 'qb_ge_b', 'guard_name' => 'web']);

    $higher = $role_a->id >= $role_b->id ? $role_a : $role_b;
    $lower = $role_a->id >= $role_b->id ? $role_b : $role_a;

    $user_lower = User::factory()->create();
    $user_lower->assignRole($lower);

    $user_higher = User::factory()->create();
    $user_higher->assignRole($higher);

    $filters = new FiltersGroup([
        new Filter('roles.id', $higher->id, FilterOperator::GREAT_EQUALS),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_higher->id]);
    expect($query->pluck('id')->all())->not->toContain($user_lower->id);
});

it('applies not equals on nested relation field roles.permissions.name', function (): void {
    $role_keep = Role::factory()->create(['name' => 'qb_ne_keep', 'guard_name' => 'web']);
    $role_skip = Role::factory()->create(['name' => 'qb_ne_skip', 'guard_name' => 'web']);

    $perm_target = Permission::factory()->create(['name' => 'default.qb_ne_target.select', 'guard_name' => 'web']);
    $perm_other = Permission::factory()->create(['name' => 'default.qb_ne_other.select', 'guard_name' => 'web']);

    $role_keep->givePermissionTo($perm_other);
    $role_skip->givePermissionTo($perm_target);

    $user_match = User::factory()->create();
    $user_match->assignRole($role_keep);

    $user_no = User::factory()->create();
    $user_no->assignRole($role_skip);

    $filters = new FiltersGroup([
        new Filter('roles.permissions.name', 'default.qb_ne_target.select', FilterOperator::NOT_EQUALS),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_match->id]);
    expect($query->pluck('id')->all())->not->toContain($user_no->id);
});

it('accepts table-prefixed main field in filter property', function (): void {
    $user_match = User::factory()->create(['username' => 'qb_prefixed_user']);
    User::factory()->create(['username' => 'other']);

    $filters = new FiltersGroup([
        new Filter('users.username', 'qb_prefixed_user', FilterOperator::EQUALS),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_match->id]);
});

it('applies OR group with multiple LIKE filters', function (): void {
    $user_alpha = User::factory()->create(['username' => 'prefix_alpha_suffix']);
    $user_beta = User::factory()->create(['username' => 'prefix_beta_suffix']);
    User::factory()->create(['username' => 'gamma_only']);

    $filters = new FiltersGroup([
        new Filter('username', '%alpha%', FilterOperator::LIKE),
        new Filter('username', '%beta%', FilterOperator::LIKE),
    ], WhereClause::OR);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$user_alpha->id, $user_beta->id]);
});

it('applies less than on main model column', function (): void {
    $u_low = User::factory()->create();
    $u_mid = User::factory()->create();
    $u_high = User::factory()->create();

    $filters = new FiltersGroup([
        new Filter('id', $u_mid->id, FilterOperator::LESS),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toContain($u_low->id);
    expect($query->pluck('id')->all())->not->toContain($u_mid->id);
    expect($query->pluck('id')->all())->not->toContain($u_high->id);
});

it('applies less equals on relation field roles.id', function (): void {
    $role_a = Role::factory()->create(['name' => 'qb_le_a', 'guard_name' => 'web']);
    $role_b = Role::factory()->create(['name' => 'qb_le_b', 'guard_name' => 'web']);

    $higher = $role_a->id >= $role_b->id ? $role_a : $role_b;
    $lower = $role_a->id >= $role_b->id ? $role_b : $role_a;

    $user_lower = User::factory()->create();
    $user_lower->assignRole($lower);

    $user_higher = User::factory()->create();
    $user_higher->assignRole($higher);

    $filters = new FiltersGroup([
        new Filter('roles.id', $lower->id, FilterOperator::LESS_EQUALS),
    ]);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqual([$user_lower->id]);
    expect($query->pluck('id')->all())->not->toContain($user_higher->id);
});

it('applies OR between relation between and main equals filters', function (): void {
    $role_in = Role::factory()->create(['name' => 'qb_or_between_in', 'guard_name' => 'web']);
    $role_out = Role::factory()->create(['name' => 'qb_or_between_out', 'guard_name' => 'web']);

    $perm_a = Permission::factory()->create(['name' => 'default.qb_or_between_a.select', 'guard_name' => 'web']);
    $perm_b = Permission::factory()->create(['name' => 'default.qb_or_between_b.select', 'guard_name' => 'web']);
    $perm_far = Permission::factory()->create(['name' => 'default.qb_or_between_far.select', 'guard_name' => 'web']);

    $role_in->givePermissionTo([$perm_a, $perm_b]);
    $role_out->givePermissionTo($perm_far);

    $low_id = min($perm_a->id, $perm_b->id);
    $high_id = max($perm_a->id, $perm_b->id);

    $user_relation_match = User::factory()->create(['username' => 'qb_or_between_user']);
    $user_relation_match->assignRole($role_in);

    $user_main_match = User::factory()->create(['username' => 'qb_or_target']);
    $user_main_match->assignRole($role_out);

    $user_no_match = User::factory()->create(['username' => 'qb_or_none']);
    $user_no_match->assignRole($role_out);

    $filters = new FiltersGroup([
        new Filter('roles.permissions.id', [$low_id, $high_id], FilterOperator::BETWEEN),
        new Filter('username', 'qb_or_target', FilterOperator::EQUALS),
    ], WhereClause::OR);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$user_relation_match->id, $user_main_match->id]);
    expect($query->pluck('id')->all())->not->toContain($user_no_match->id);
});

it('applies nested OR combining NOT LIKE on permissions BETWEEN on roles.id and GREAT_EQUALS on main id', function (): void {
    $role_min = Role::factory()->create(['name' => 'qb_complex_min', 'guard_name' => 'web']);
    $role_mid = Role::factory()->create(['name' => 'qb_complex_mid', 'guard_name' => 'web']);
    $role_out = Role::factory()->create(['name' => 'qb_complex_out', 'guard_name' => 'web']);
    $role_safe = Role::factory()->create(['name' => 'qb_complex_safe', 'guard_name' => 'web']);

    $perm_good = Permission::factory()->create(['name' => 'default.qbcomplexok.select', 'guard_name' => 'web']);
    $perm_bad = Permission::factory()->create(['name' => 'default.qbcomplexbadtoken.select', 'guard_name' => 'web']);

    $role_safe->givePermissionTo($perm_good);
    $role_mid->givePermissionTo($perm_bad);
    $role_out->givePermissionTo($perm_bad);

    $low_between = min($role_min->id, $role_mid->id);
    $high_between = max($role_min->id, $role_mid->id);

    $user_none = User::factory()->create(['username' => 'qb_complex_none']);
    $user_none->assignRole($role_out);

    $user_not_like = User::factory()->create(['username' => 'qb_complex_nl']);
    $user_not_like->assignRole($role_safe);

    $user_between = User::factory()->create(['username' => 'qb_complex_btwn']);
    $user_between->assignRole($role_mid);

    // Created last so `GREAT_EQUALS` on this id does not include other fixture users.
    $user_ge = User::factory()->create(['username' => 'qb_complex_ge']);

    $filters = new FiltersGroup([
        new FiltersGroup([
            new Filter('roles.permissions.name', '%qbcomplexbadtoken%', FilterOperator::NOT_LIKE),
            new Filter('roles.id', [$low_between, $high_between], FilterOperator::BETWEEN),
        ], WhereClause::OR),
        new Filter('id', $user_ge->id, FilterOperator::GREAT_EQUALS),
    ], WhereClause::OR);

    $candidate_ids = [$user_not_like->id, $user_between->id, $user_ge->id, $user_none->id];

    $query = User::query()->whereKey($candidate_ids);
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$user_not_like->id, $user_between->id, $user_ge->id]);
    expect($query->pluck('id')->all())->not->toContain($user_none->id);
});

it('keeps query unchanged when OR filter group is empty', function (): void {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $filters = new FiltersGroup([], WhereClause::OR);

    $query = User::query()->whereKey([$u1->id, $u2->id]);
    (new QueryBuilder())->applyFilters($query, $filters);

    expect($query->pluck('id')->all())->toEqualCanonicalizing([$u1->id, $u2->id]);
});
