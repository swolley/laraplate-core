<?php

declare(strict_types=1);

use Modules\Core\Models\Modification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

/**
 * @return array{module: string, entity: string}
 */
function pendingApprovalsRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'settings'];
}

it('lists active modifications for an entity when the user can approve', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'pending_inbox_' . uniqid(),
        'value' => 'original',
    ]);

    $author = User::factory()->create();
    $approver = User::factory()->create();
    $approver->assignRole(Role::findOrCreate('superadmin', 'web'));

    Modification::query()->create([
        'modifiable_type' => Setting::class,
        'modifiable_id' => $setting->getKey(),
        'modifier_id' => $author->getKey(),
        'modifier_type' => $author::class,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 2,
        'disapprovers_required' => 1,
        'md5' => md5('pending-inbox'),
        'modifications' => [
            'value' => ['original' => 'original', 'modified' => 'changed'],
        ],
    ]);

    Modification::query()->create([
        'modifiable_type' => Setting::class,
        'modifiable_id' => $setting->getKey(),
        'modifier_id' => $author->getKey(),
        'modifier_type' => $author::class,
        'active' => false,
        'is_update' => true,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('inactive-inbox'),
        'modifications' => [
            'value' => ['original' => 'x', 'modified' => 'y'],
        ],
    ]);

    $response = $this->actingAs($approver)->getJson(
        route('core.crud.pending-approvals', pendingApprovalsRouteParams()),
    );

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toBeArray()->toHaveCount(1)
        ->and($data[0]['id'])->toEqual($setting->getKey())
        ->and($data[0]['modification_id'])->not->toBeNull()
        ->and($data[0]['title'])->toBe($setting->name);
});

it('allows pending approvals with the approve permission on the entity table', function (): void {
    $approver = User::factory()->create();
    Permission::findOrCreate('default.core_settings.approve', 'web');
    $approver->givePermissionTo('default.core_settings.approve');

    $response = $this->actingAs($approver)->getJson(
        route('core.crud.pending-approvals', pendingApprovalsRouteParams()),
    );

    $response->assertOk();
    expect($response->json('data'))->toBeArray();
});

it('denies pending approvals listing without approve permission', function (): void {
    $operator = User::factory()->create();

    $response = $this->actingAs($operator)->getJson(
        route('core.crud.pending-approvals', pendingApprovalsRouteParams()),
    );

    $response->assertStatus(Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
});
