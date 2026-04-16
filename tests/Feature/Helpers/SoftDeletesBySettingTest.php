<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

beforeEach(function (): void {
    Cache::forget('soft_deletes_flags');
});

it('soft deletes by default when no setting is defined', function (): void {
    $user = User::factory()->create();
    $id = $user->id;
    $user->delete();

    expect(User::query()->find($id))->toBeNull();
    expect(User::query()->withTrashed()->find($id))->not->toBeNull();
    expect(User::query()->withTrashed()->find($id)?->trashed())->toBeTrue();
});

it('performs hard delete when soft_deletes setting is disabled for the model table', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'soft_deletes_users',
        'group_name' => 'soft_deletes',
        'type' => SettingTypeEnum::BOOLEAN,
        'value' => false,
    ]);

    $user = User::factory()->create();
    $id = $user->id;
    $user->delete();

    expect(User::query()->withTrashed()->find($id))->toBeNull();
});

it('returns false from restore when soft deletes persistence is disabled', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'soft_deletes_users',
        'group_name' => 'soft_deletes',
        'type' => SettingTypeEnum::BOOLEAN,
        'value' => false,
    ]);

    $user = User::factory()->create();

    expect($user->restore())->toBeFalse();
});
