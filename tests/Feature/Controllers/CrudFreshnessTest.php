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
        ->and(count($data['items']))->toBeLessThanOrEqual($pageSize + 1)
        ->and($data['presence'] ?? [])->toBeArray()->toBeEmpty();

    foreach ($data['items'] as $item) {
        expect($item)->toHaveKeys(['id', 'updated_at'])
            ->and($item['updated_at'])->not->toBeNull();
    }
});

it('classifies check_ids as on_page, off_page, or gone', function (): void {
    // listByPagination uses to = from + pagination → window size = pagination + 1.
    $pageSize = 2;
    $windowSize = $pageSize + 1;

    $settings = collect();
    foreach (range(1, $windowSize + 1) as $i) {
        $settings->push(
            Setting::factory()->persistedWithoutApprovalCapture()->create([
                'name' => 'freshness_presence_' . $i . '_' . uniqid(),
            ]),
        );
    }

    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('superadmin', 'web'));

    $sorted = $settings->sortByDesc('id')->values();
    $onPage = $sorted->take($windowSize);
    $offPage = $sorted->get($windowSize);
    $goneId = 9_999_999_001;

    $checkIds = [
        ...$onPage->pluck('id')->all(),
        $offPage->id,
        $goneId,
    ];

    $response = $this->actingAs($viewer)->getJson(
        route('core.crud.freshness', freshnessRouteParams())
            . '?' . http_build_query([
                'page' => 1,
                'pagination' => $pageSize,
                'sort' => [
                    ['property' => 'id', 'direction' => 'desc'],
                ],
                'check_ids' => $checkIds,
            ]),
    );

    $response->assertOk();
    $presence = collect($response->json('data.presence'));
    expect($presence)->toHaveCount(count($checkIds));

    foreach ($onPage as $setting) {
        $row = $presence->firstWhere('id', $setting->id);
        expect($row)->not->toBeNull()
            ->and($row['status'])->toBe('on_page')
            ->and($row['updated_at'])->not->toBeNull();
    }

    $offRow = $presence->firstWhere('id', $offPage->id);
    expect($offRow)->not->toBeNull()
        ->and($offRow['status'])->toBe('off_page')
        ->and($offRow['updated_at'])->not->toBeNull();

    $goneRow = $presence->firstWhere('id', $goneId);
    expect($goneRow)->not->toBeNull()
        ->and($goneRow['status'])->toBe('gone')
        ->and($goneRow['updated_at'])->toBeNull();
});

it('marks deleted snapshot ids as gone while siblings stay on_page', function (): void {
    $a = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'freshness_del_a_' . uniqid(),
    ]);
    $b = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'freshness_del_b_' . uniqid(),
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('superadmin', 'web'));

    $deletedId = $a->id;
    $a->delete();

    $response = $this->actingAs($viewer)->getJson(
        route('core.crud.freshness', freshnessRouteParams())
            . '?' . http_build_query([
                'page' => 1,
                'pagination' => 50,
                'check_ids' => [$deletedId, $b->id],
            ]),
    );

    $response->assertOk();
    $presence = collect($response->json('data.presence'));

    expect($presence->firstWhere('id', $deletedId)['status'])->toBe('gone')
        ->and($presence->firstWhere('id', $b->id)['status'])->toBe('on_page');
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
