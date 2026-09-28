<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Modifications\Pages\ListModifications;
use Modules\Core\Filament\Resources\Settings\Pages\EditSetting;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Setting;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Support\HttpContext;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * The author reaches Modifications to withdraw their requests: grant it the list besides the setting actions.
 */
function approvalOutcomeAuthor(): void
{
    $actor = HttpContext::panelActorWithoutApproval(new Setting);
    $permission = PermissionName::forModel(new Modification, 'select');
    Permission::findOrCreate($permission, 'web');
    $actor->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

beforeEach(function (): void {
    $this->setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'x', 'choices' => null, 'is_public' => false]);
});

it('reports a save blocked by a pending deletion', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting);
    $this->setting->delete();

    Livewire::test(EditSetting::class, ['record' => $this->setting->getKey()])
        ->fillForm(['is_public' => true])
        ->call('save')
        ->assertNotified('A deletion of this record is waiting for approval');

    expect($this->setting->fresh()->is_public)->toBeFalse();
});

it('offers the author a withdraw action in Modifications', function (): void {
    approvalOutcomeAuthor();
    $this->setting->delete();
    $modification = $this->setting->pendingModification();

    Livewire::test(ListModifications::class)
        ->assertTableActionVisible('withdraw', $modification)
        ->callTableAction('withdraw', $modification);

    expect(Modification::query()->find($modification->id))->toBeNull();
});

it('hides the withdraw action from anybody but the author', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting);
    $this->setting->delete();
    $modification = $this->setting->pendingModification();

    approvalOutcomeAuthor();

    Livewire::test(ListModifications::class)->assertTableActionHidden('withdraw', $modification);
});
