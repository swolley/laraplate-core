<?php

declare(strict_types=1);

use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Modifications\ModificationResource;
use Modules\Core\Filament\Resources\Modifications\Pages\ListModifications;
use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Models\License;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

function modificationPanelUser(): User
{
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    /** @var App\Models\User $user */
    $user = App\Models\User::query()->create(User::factory()->raw());
    $user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    return $user;
}

/**
 * @return array{0: Setting, 1: Modification}
 */
function pendingSettingModification(User $author): array
{
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'original',
        'choices' => null,
    ]);

    $modification = Modification::query()->create([
        'modifiable_type' => Setting::class,
        'modifiable_id' => $setting->getKey(),
        'modifier_id' => $author->getKey(),
        'modifier_type' => $author::class,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('panel-vote-' . uniqid()),
        'modifications' => [
            'value' => ['original' => 'original', 'modified' => 'changed'],
        ],
    ]);

    return [$setting, $modification];
}

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('offers no create, edit or delete on modifications', function (): void {
    $author = modificationPanelUser();
    [, $modification] = pendingSettingModification($author);

    $this->actingAs(modificationPanelUser());

    Livewire::test(ListModifications::class)
        ->assertActionHidden(CreateAction::class)
        ->assertTableActionHidden('edit', $modification)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableActionHidden('forceDelete', $modification)
        ->assertTableBulkActionDoesNotExist('delete')
        ->assertTableBulkActionHidden('forceDelete');

    expect(ModificationResource::getPages())->toHaveKeys(['index'])
        ->not->toHaveKey('create')
        ->not->toHaveKey('edit')
        ->and(ModificationResource::canEdit($modification))->toBeFalse();
});

it('applies the change when an approver approves from the panel', function (): void {
    $author = modificationPanelUser();
    [$setting, $modification] = pendingSettingModification($author);

    $this->actingAs(modificationPanelUser());

    Livewire::test(ListModifications::class)
        ->assertTableActionVisible('approve', $modification)
        ->callTableAction('approve', $modification, ['reason' => 'Looks right'])
        ->assertHasNoTableActionErrors();

    expect($setting->fresh()->value)->toBe('changed')
        ->and($modification->fresh()?->active)->toBeFalsy();
});

it('discards the change when an approver disapproves from the panel', function (): void {
    $author = modificationPanelUser();
    [$setting, $modification] = pendingSettingModification($author);

    $this->actingAs(modificationPanelUser());

    Livewire::test(ListModifications::class)
        ->callTableAction('disapprove', $modification)
        ->assertHasNoTableActionErrors();

    expect($setting->fresh()->value)->toBe('original');
});

it('hides the vote actions from the author of the modification', function (): void {
    $author = modificationPanelUser();
    [, $modification] = pendingSettingModification($author);

    $this->actingAs($author);

    Livewire::test(ListModifications::class)
        ->assertTableActionHidden('approve', $modification)
        ->assertTableActionHidden('disapprove', $modification);
});

it('shows the pending change read-only', function (): void {
    $author = modificationPanelUser();
    [, $modification] = pendingSettingModification($author);

    $this->actingAs(modificationPanelUser());

    Livewire::test(ListModifications::class)
        ->mountTableAction('view', $modification)
        ->assertTableActionDataSet([
            'modifiable_type' => Setting::class,
            'modifications' => json_encode(
                ['value' => ['original' => 'original', 'modified' => 'changed']],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        ]);
});

it('hides modifications when no active model goes through approvals', function (): void {
    modificationPanelUser();
    HelpersCache::setModels('active', [License::class]);

    try {
        expect(ModificationResource::canAccess())->toBeFalse();

        Livewire::test(ListModifications::class)->assertForbidden();
    } finally {
        HelpersCache::clearModels();
    }
});

it('shows modifications when an active model goes through approvals', function (): void {
    modificationPanelUser();

    expect(ModificationResource::canAccess())->toBeTrue();

    Livewire::test(ListModifications::class)->assertOk();
});
