<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Tests\Support\HttpContext;

/**
 * Model validation runs on creating/updating, which a captured write never reaches. The capture
 * validates first, so a pending request only ever carries data the model accepts.
 */
it('refuses to capture a write the model rules reject', function (): void {
    // Reloaded: the factory state leaves its instance flagged to skip validation.
    $setting = Setting::query()->findOrFail(Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'integer', 'value' => 1, 'choices' => null])->id);
    HttpContext::pretendHttpRequest();
    Auth::login(User::factory()->create());

    $setting->value = 'not a number';

    expect(fn () => $setting->save())->toThrow(ValidationException::class)
        ->and(Modification::query()->count())->toBe(0);
});

it('still captures a valid write', function (): void {
    // Reloaded: the factory state leaves its instance flagged to skip validation.
    $setting = Setting::query()->findOrFail(Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'integer', 'value' => 1, 'choices' => null])->id);
    HttpContext::pretendHttpRequest();
    Auth::login(User::factory()->create());

    $setting->value = 2;

    expect($setting->save())->toBeFalse()
        ->and($setting->pendingModification()?->modifications['value']['modified'] ?? null)->toBe(2);
});
