<?php

declare(strict_types=1);

use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

/**
 * @return array{module: string, entity: string}
 */
function freshnessRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'settings'];
}

it('returns page-scoped id and updated_at fingerprints with filtered total', function (): void {
    foreach (range(1, 5) as $i) {
        Setting::factory()->persistedWithoutApprovalCapture()->create([
            'name' => 'freshness_page_' . $i . '_' . uniqid(),
        ]);
    }

    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('superadmin', 'web'));

    $pageSize = 2;

    $response = $this->actingAs($viewer)->getJson(
        route('core.crud.freshness', freshnessRouteParams())
            . '?' . http_build_query(['page' => 1, 'pagination' => $pageSize]),
    );

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toBeArray()
        ->and($data['total'])->toBeInt()->toBeGreaterThanOrEqual(5)
        ->and($data['items'])->toBeArray()
        // Same window as select (`listByPagination`); must be page-scoped, not the full set.
        ->and(count($data['items']))->toBeLessThan($data['total'])
        ->and(count($data['items']))->toBeLessThanOrEqual($pageSize + 1);

    foreach ($data['items'] as $item) {
        expect($item)->toHaveKeys(['id', 'updated_at'])
            ->and($item['updated_at'])->not->toBeNull();
    }
});

it('allows freshness with the select permission on the entity table', function (): void {
    foreach (range(1, 2) as $i) {
        Setting::factory()->persistedWithoutApprovalCapture()->create([
            'name' => 'freshness_perm_' . $i . '_' . uniqid(),
        ]);
    }

    $viewer = User::factory()->create();
    Permission::findOrCreate('default.core_settings.select', 'web');
    $viewer->givePermissionTo('default.core_settings.select');

    $response = $this->actingAs($viewer)->getJson(
        route('core.crud.freshness', freshnessRouteParams())
            . '?' . http_build_query(['page' => 1, 'pagination' => 10]),
    );

    $response->assertOk();
    expect($response->json('data.items'))->toBeArray();
});

it('denies freshness without select permission', function (): void {
    $operator = User::factory()->create();

    $response = $this->actingAs($operator)->getJson(
        route('core.crud.freshness', freshnessRouteParams()),
    );

    // AuthorizationException is mapped to 401 by CrudController (sibling CRUD endpoints).
    $response->assertStatus(Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
});
