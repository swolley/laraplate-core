<?php

declare(strict_types=1);

use Modules\Core\Models\Modification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Support\HttpContext;

/**
 * A writer who may change settings but not approve the change: every write is captured.
 */
function crudApprovalWriter(): User
{
    $writer = User::factory()->create();

    foreach (['select', 'update', 'delete', 'forceDelete'] as $action) {
        $permission = PermissionName::forModel(new Setting, $action);
        Permission::findOrCreate($permission, 'web');
        $writer->givePermissionTo($permission);
    }

    return $writer;
}

/**
 * @return array{module: string, entity: string}
 */
function crudApprovalRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'settings'];
}

beforeEach(function (): void {
    $this->setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'x', 'choices' => null]);
    $this->author = crudApprovalWriter();
    $this->other_writer = crudApprovalWriter();
    $this->actingAs($this->author);
    HttpContext::pretendHttpRequest();
});

it('answers 202 with the modification when an update is captured', function (): void {
    $response = $this->patchJson(route('core.crud.replace', crudApprovalRouteParams()), ['id' => $this->setting->id, 'is_public' => true]);

    $response->assertStatus(202)->assertJsonPath('data.operation', 'update');

    expect($this->setting->fresh()->is_public)->toBeFalse();
});

it('answers 202 when a delete is captured and 409 when the blocked record is then updated', function (): void {
    $this->deleteJson(route('core.crud.delete', crudApprovalRouteParams()), ['id' => $this->setting->id])
        ->assertStatus(202)->assertJsonPath('data.operation', 'force_delete');

    $this->patchJson(route('core.crud.replace', crudApprovalRouteParams()), ['id' => $this->setting->id, 'is_public' => true])
        ->assertStatus(409);

    expect(Setting::query()->whereKey($this->setting->id)->exists())->toBeTrue();
});

it('lets the author withdraw through the API', function (): void {
    $modification_id = $this->patchJson(route('core.crud.replace', crudApprovalRouteParams()), ['id' => $this->setting->id, 'is_public' => true])
        ->assertStatus(202)->json('data.modification');

    $this->patchJson(route('core.crud.withdraw', crudApprovalRouteParams()), ['id' => $this->setting->id, 'modification' => $modification_id])
        ->assertOk();

    expect(Modification::query()->find($modification_id))->toBeNull();
});

it('refuses a withdrawal through the API from anybody but the author', function (): void {
    $this->setting->is_public = true;
    $this->setting->save();
    $modification = $this->setting->pendingModification();

    // CrudController answers 403 to an authenticated user an AuthorizationException refuses, 401 to the anonymous user.
    $this->actingAs($this->other_writer)
        ->patchJson(route('core.crud.withdraw', crudApprovalRouteParams()), ['id' => $this->setting->id, 'modification' => $modification->id])
        ->assertStatus(403);

    expect(Modification::query()->find($modification->id))->not->toBeNull();
});

it('answers 409 when the request to withdraw is already decided', function (): void {
    $modification_id = $this->patchJson(route('core.crud.replace', crudApprovalRouteParams()), ['id' => $this->setting->id, 'is_public' => true])
        ->json('data.modification');
    Modification::query()->whereKey($modification_id)->update(['active' => false]);

    $this->patchJson(route('core.crud.withdraw', crudApprovalRouteParams()), ['id' => $this->setting->id, 'modification' => $modification_id])
        ->assertStatus(409);
});
