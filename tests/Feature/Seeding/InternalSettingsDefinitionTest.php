<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;

it('keeps an operator chosen group across re-seeds', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $setting = Setting::query()->withoutGlobalScopes()->where('is_internal', true)->firstOrFail();
    $seeded_group = $setting->group_name;

    Setting::query()->withoutGlobalScopes()
        ->whereKey($setting->getKey())
        ->update(['group_name' => 'operator_group', 'description' => 'drifted description']);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $fresh = $setting->fresh();

    expect($fresh->group_name)->toBe('operator_group')
        ->and($fresh->group_name)->not->toBe($seeded_group)
        ->and($fresh->description)->not->toBe('drifted description');
});
