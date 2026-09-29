<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Settings\Pages\ListSettings;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Stubs\Console\SettingActionProbeCommand;
use Modules\Core\Tests\Support\HttpContext;

beforeEach(function (): void {
    SettingActionProbeCommand::$calls = [];
    app(ConsoleKernel::class)->registerCommand(new SettingActionProbeCommand);
});

function settingActionTableFixture(?string $command, array $attributes = []): Setting
{
    return Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'probe.grid',
        'type' => 'string',
        'value' => 'plain',
        'choices' => null,
        'encrypted' => false,
        'action_command' => $command,
        'action_queued' => false,
        ...$attributes,
    ]);
}

it('hides the action on a setting without one', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture(null);

    Livewire::test(ListSettings::class)->assertTableActionHidden('runSettingAction', $setting);
});

it('hides the action from a user who cannot update settings', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {name}');

    Livewire::test(ListSettings::class)->assertTableActionHidden('runSettingAction', $setting);
});

it('runs the command from the row and reports its output', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {name}');

    Livewire::test(ListSettings::class)
        ->assertTableActionVisible('runSettingAction', $setting)
        ->callTableAction('runSettingAction', $setting)
        ->assertNotified('Command completed');

    expect(SettingActionProbeCommand::$calls)->toBe([['name' => 'probe.grid', 'flag' => null]]);
});

it('reports a failing command', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {name} --fail');

    Livewire::test(ListSettings::class)
        ->callTableAction('runSettingAction', $setting)
        ->assertNotified('Command failed');
});

it('reports a refused action without running it', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {nope}');

    Livewire::test(ListSettings::class)
        ->callTableAction('runSettingAction', $setting)
        ->assertNotified('Action refused');

    expect(SettingActionProbeCommand::$calls)->toBe([]);
});

it('queues the command when the setting asks for it', function (): void {
    Bus::fake();
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {name}', ['action_queued' => true]);

    Livewire::test(ListSettings::class)
        ->callTableAction('runSettingAction', $setting)
        ->assertNotified('Command queued');

    Bus::assertDispatched(QueuedCommand::class);
});
