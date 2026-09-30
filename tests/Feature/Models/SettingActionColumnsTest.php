<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\MassAssignmentException;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Support\HttpContext;

function settingActionColumnsFixture(array $attributes = []): Setting
{
    return Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'a',
        'choices' => ['a'],
        'encrypted' => false,
        ...$attributes,
    ]);
}

it('defaults action_queued to false', function (): void {
    expect((new Setting)->action_queued)->toBeFalse();
});

it('refuses to mass assign the action command', function (): void {
    (new Setting)->fill(['action_command' => 'probe:run']);
})->throws(MassAssignmentException::class);

it('stores the action columns but never serializes them', function (): void {
    $setting = settingActionColumnsFixture(['action_command' => 'probe:run {name}', 'action_queued' => true])->fresh();

    expect($setting->action_command)->toBe('probe:run {name}')
        ->and($setting->action_queued)->toBeTrue()
        ->and($setting->toArray())->not->toHaveKeys(['action_command', 'action_queued']);
});

it('writes a choices-only change directly for a writer who would otherwise be captured', function (): void {
    $setting = settingActionColumnsFixture();
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);

    $fresh = $setting->fresh();
    $fresh->choices = ['a', 'b'];
    $fresh->save();

    expect($setting->fresh()->choices)->toBe(['a', 'b'])
        ->and($setting->modifications()->activeOnly()->exists())->toBeFalse();
});

it('still captures a value change from the same writer', function (): void {
    $setting = settingActionColumnsFixture(['choices' => ['a', 'b']]);
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);

    $fresh = $setting->fresh();
    $fresh->value = 'b';
    $fresh->save();

    expect($setting->fresh()->value)->toBe('a')
        ->and($setting->modifications()->activeOnly()->exists())->toBeTrue();
});
