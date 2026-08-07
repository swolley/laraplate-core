<?php

declare(strict_types=1);

use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * @return array{module: string, entity: string}
 */
function facetsRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'settings'];
}

function seedFacetSettings(): void
{
    Setting::factory()->persistedWithoutApprovalCapture()->create(['name' => 'facet_a1_' . uniqid(), 'group_name' => 'A']);
    Setting::factory()->persistedWithoutApprovalCapture()->create(['name' => 'facet_a2_' . uniqid(), 'group_name' => 'A']);
    Setting::factory()->persistedWithoutApprovalCapture()->create(['name' => 'facet_b1_' . uniqid(), 'group_name' => 'B']);
}

it('returns facet counts over the entity via a standalone endpoint', function (): void {
    seedFacetSettings();

    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('superadmin', 'web'));

    $response = $this->actingAs($viewer)->getJson(
        route('core.crud.facets', facetsRouteParams())
            . '?' . http_build_query(['columns' => ['group_name']]),
    );

    $response->assertOk();

    $by_value = collect($response->json('data.group_name'))->keyBy('value');

    expect($by_value['A']['total'])->toBe(2)
        ->and($by_value['A']['count'])->toBe(2)
        ->and($by_value['B']['total'])->toBe(1)
        ->and($by_value['B']['count'])->toBe(1);
});

it('denies facets without the select permission on the entity', function (): void {
    $operator = User::factory()->create();

    $response = $this->actingAs($operator)->getJson(
        route('core.crud.facets', facetsRouteParams())
            . '?' . http_build_query(['columns' => ['group_name']]),
    );

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});
