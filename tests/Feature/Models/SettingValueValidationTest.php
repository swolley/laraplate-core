<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

/**
 * @param  array<string, mixed>  $attributes
 */
function settingWithValue(array $attributes): Setting
{
    return Setting::query()->create([
        'name' => 'value_rule_' . uniqid(),
        'group_name' => 'base',
        'encrypted' => false,
        'description' => 'Value rule test',
        'choices' => null,
        ...$attributes,
    ]);
}

it('saves a value that matches the setting type', function (string $type, mixed $value): void {
    expect(settingWithValue(['type' => $type, 'value' => $value])->fresh()->value)->toEqual($value);
})->with([
    'boolean' => ['boolean', true],
    'integer' => ['integer', 42],
    'float' => ['float', 1.5],
    'string' => ['string', 'text'],
    'date' => ['date', '2026-09-29'],
    'json' => ['json', ['any' => ['shape']]],
]);

it('refuses a value that does not match the setting type', function (string $type, mixed $value): void {
    expect(fn () => settingWithValue(['type' => $type, 'value' => $value]))->toThrow(ValidationException::class);
})->with([
    'boolean' => ['boolean', 'maybe'],
    'integer' => ['integer', 'forty-two'],
    'float' => ['float', 'one and a half'],
    'string' => ['string', ['not', 'a', 'string']],
    'date' => ['date', 'not a date'],
]);

it('keeps a value within the setting choices', function (): void {
    expect(fn () => settingWithValue(['type' => 'string', 'choices' => ['a', 'b'], 'value' => 'c']))->toThrow(ValidationException::class)
        ->and(settingWithValue(['type' => 'string', 'choices' => ['a', 'b'], 'value' => 'b'])->value)->toBe('b')
        ->and(fn () => settingWithValue(['type' => 'json', 'choices' => ['a', 'b'], 'value' => ['a', 'c']]))->toThrow(ValidationException::class)
        ->and(settingWithValue(['type' => 'json', 'choices' => ['a', 'b'], 'value' => ['a', 'b']])->value)->toBe(['a', 'b']);
});

it('keeps a saved value the refreshed choices no longer offer, and refuses to pick another one outside them', function (): void {
    $setting = settingWithValue(['type' => 'string', 'choices' => ['openai:gpt-3.5', 'openai:gpt-4o'], 'value' => 'openai:gpt-3.5']);

    $setting->choices = ['openai:gpt-4o'];
    $setting->save();

    expect($setting->fresh()->value)->toBe('openai:gpt-3.5')
        ->and($setting->fresh()->isValueOutsideChoices())->toBeTrue();

    $setting->value = 'openai:gpt-2';

    expect(fn () => $setting->save())->toThrow(ValidationException::class);
});

it('validates an API update against the stored type when the request does not send it', function (): void {
    $setting = settingWithValue(['type' => 'integer', 'value' => 1]);
    $superadmin = User::factory()->create();
    $superadmin->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    $route = route('core.crud.replace', ['module' => 'core', 'entity' => 'settings']);

    $this->actingAs($superadmin)->patchJson($route, ['id' => $setting->id, 'value' => 'forty-two'])->assertUnprocessable();
    $this->patchJson($route, ['id' => $setting->id, 'value' => 42])->assertOk();

    expect($setting->fresh()->value)->toBe(42);
});
