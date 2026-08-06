<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Models\User;

it('profiles the crud list engine as a transient superadmin and rolls back', function (): void {
    $users_before = User::query()->count();

    $exit = Artisan::call('perf:crud', [
        '--module' => 'core',
        '--entity' => ['users'],
        '--iterations' => 2,
        '--warmup' => 0,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('core/users')
        ->and($output)->toContain('200')
        // The transient superadmin created for the run must have been rolled back.
        ->and(User::query()->count())->toBe($users_before);
});

it('fails when no entity is provided', function (): void {
    $exit = Artisan::call('perf:crud', ['--module' => 'core']);

    expect($exit)->toBe(1);
});
