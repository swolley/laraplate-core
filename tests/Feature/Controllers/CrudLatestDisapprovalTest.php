<?php

declare(strict_types=1);

use Modules\Core\Models\Disapproval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

/**
 * @return array{module: string, entity: string}
 */
function latestDisapprovalRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'settings'];
}

it('returns the latest soft-kept disapproval for the modifier', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'reject_banner_' . uniqid(),
        'value' => 'original',
    ]);

    $author = User::factory()->create();
    $author->assignRole(Role::findOrCreate('superadmin', 'web'));
    $publisher = User::factory()->create();

    $modification = Modification::query()->create([
        'modifiable_type' => Setting::class,
        'modifiable_id' => $setting->getKey(),
        'modifier_id' => $author->getKey(),
        'modifier_type' => $author::class,
        'active' => false,
        'is_update' => true,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('rejected-banner'),
        'modifications' => [
            'value' => ['original' => 'original', 'modified' => 'rejected-value'],
            'name' => ['original' => $setting->name, 'modified' => 'proposed-name'],
        ],
    ]);

    Disapproval::query()->create([
        'modification_id' => $modification->getKey(),
        'disapprover_id' => $publisher->getKey(),
        'disapprover_type' => $publisher::class,
        'reason' => 'Titolo poco chiaro',
    ]);

    // Active pending must not be returned as rejection feedback.
    Modification::query()->create([
        'modifiable_type' => Setting::class,
        'modifiable_id' => $setting->getKey(),
        'modifier_id' => $author->getKey(),
        'modifier_type' => $author::class,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('still-active'),
        'modifications' => [
            'value' => ['original' => 'original', 'modified' => 'pending'],
        ],
    ]);

    $response = $this->actingAs($author)->getJson(
        route('core.crud.latest-disapproval', latestDisapprovalRouteParams())
            . '?' . http_build_query(['id' => $setting->getKey()]),
    );

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toBeArray()
        ->and($data['modification_id'])->toEqual($modification->getKey())
        ->and($data['reason'])->toBe('Titolo poco chiaro')
        ->and($data['modifications']['value']['modified'])->toBe('rejected-value');
});

it('returns null data when the current user has no soft-kept disapproval', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'no_reject_' . uniqid(),
        'value' => 'x',
    ]);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));

    $response = $this->actingAs($user)->getJson(
        route('core.crud.latest-disapproval', latestDisapprovalRouteParams())
            . '?' . http_build_query(['id' => $setting->getKey()]),
    );

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('denies latest disapproval without select permission', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'deny_reject_' . uniqid(),
        'value' => 'x',
    ]);
    $operator = User::factory()->create();

    $response = $this->actingAs($operator)->getJson(
        route('core.crud.latest-disapproval', latestDisapprovalRouteParams())
            . '?' . http_build_query(['id' => $setting->getKey()]),
    );

    $response->assertStatus(Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
});

it('allows latest disapproval with select permission on the entity table', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'select_reject_' . uniqid(),
        'value' => 'x',
    ]);
    $operator = User::factory()->create();
    Permission::findOrCreate('default.core_settings.select', 'web');
    $operator->givePermissionTo('default.core_settings.select');

    $response = $this->actingAs($operator)->getJson(
        route('core.crud.latest-disapproval', latestDisapprovalRouteParams())
            . '?' . http_build_query(['id' => $setting->getKey()]),
    );

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});
