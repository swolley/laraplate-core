<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Exceptions\InvalidSettingActionException;
use Modules\Core\Models\Setting;
use Modules\Core\Services\SettingActionRunner;
use Modules\Core\Tests\Stubs\Console\SettingActionProbeCommand;

beforeEach(function (): void {
    SettingActionProbeCommand::$calls = [];
    app(ConsoleKernel::class)->registerCommand(new SettingActionProbeCommand);
});

function settingActionRunnerFixture(string $command, array $attributes = []): Setting
{
    return Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'probe.setting',
        'type' => 'string',
        'value' => 'plain',
        'choices' => null,
        'encrypted' => false,
        'action_command' => $command,
        'action_queued' => false,
        ...$attributes,
    ])->fresh();
}

it('quotes each substituted placeholder', function (): void {
    $line = app(SettingActionRunner::class)
        ->commandLine(settingActionRunnerFixture('laraplate:setting-action-probe {name}'));

    expect($line)->toBe('laraplate:setting-action-probe "probe.setting"');
});

it('passes a value with quotes, backslashes and a leading option as one argument', function (): void {
    $description = 'He said "hi" \ --flag=injected';
    $setting = settingActionRunnerFixture('laraplate:setting-action-probe {description}', ['description' => $description]);

    app(SettingActionRunner::class)->run($setting);

    expect(SettingActionProbeCommand::$calls)->toBe([['name' => $description, 'flag' => null]]);
});

it('substitutes placeholders inside an option', function (): void {
    app(SettingActionRunner::class)->run(settingActionRunnerFixture('laraplate:setting-action-probe x --flag={name}'));

    expect(SettingActionProbeCommand::$calls)->toBe([['name' => 'x', 'flag' => 'probe.setting']]);
});

it('encodes list and boolean values', function (): void {
    $runner = app(SettingActionRunner::class);

    $list = settingActionRunnerFixture('laraplate:setting-action-probe {value}', ['type' => 'json', 'value' => ['a', 'b']]);
    $flag = settingActionRunnerFixture('laraplate:setting-action-probe {value}', ['name' => 'probe.flag', 'type' => 'boolean', 'value' => true]);

    expect($runner->commandLine($list))->toBe('laraplate:setting-action-probe "[\"a\",\"b\"]"')
        ->and($runner->commandLine($flag))->toBe('laraplate:setting-action-probe "true"');
});

it('refuses an unknown placeholder without running anything', function (): void {
    expect(fn () => app(SettingActionRunner::class)->run(settingActionRunnerFixture('laraplate:setting-action-probe {nope}')))
        ->toThrow(InvalidSettingActionException::class);

    expect(SettingActionProbeCommand::$calls)->toBe([]);
});

it('refuses the value of an encrypted setting but runs its other placeholders', function (): void {
    $runner = app(SettingActionRunner::class);

    expect(fn () => $runner->run(settingActionRunnerFixture('laraplate:setting-action-probe {value}', ['encrypted' => true])))
        ->toThrow(InvalidSettingActionException::class);

    $result = $runner->run(settingActionRunnerFixture('laraplate:setting-action-probe {name}', ['name' => 'probe.secret', 'encrypted' => true]));

    expect($result->succeeded())->toBeTrue();
});

it('refuses a command that is not registered', function (): void {
    app(SettingActionRunner::class)->run(settingActionRunnerFixture('laraplate:no-such-command {name}'));
})->throws(InvalidSettingActionException::class);

it('returns exit code and output of a synchronous run', function (): void {
    $runner = app(SettingActionRunner::class);

    $ok = $runner->run(settingActionRunnerFixture('laraplate:setting-action-probe {name}'));
    $failed = $runner->run(settingActionRunnerFixture('laraplate:setting-action-probe {name} --fail', ['name' => 'probe.failing']));

    expect($ok->queued)->toBeFalse()
        ->and($ok->succeeded())->toBeTrue()
        ->and($ok->output)->toContain('probe received probe.setting')
        ->and($failed->succeeded())->toBeFalse()
        ->and($failed->exitCode)->toBe(1);
});

it('queues the command when action_queued is set', function (): void {
    Bus::fake();

    $result = app(SettingActionRunner::class)
        ->run(settingActionRunnerFixture('laraplate:setting-action-probe {name}', ['action_queued' => true]));

    expect($result->queued)->toBeTrue()
        ->and(SettingActionProbeCommand::$calls)->toBe([]);

    Bus::assertDispatched(QueuedCommand::class);
});
