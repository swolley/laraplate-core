<?php

declare(strict_types=1);

use App\Models\User as AppUser;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Modules\Core\Models\License;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

beforeEach(function (): void {
    License::factory()->create();
    $this->user = User::factory()->create(['password' => 'secret-password']);
    $this->original_hash = $this->user->fresh()?->password;
});

it('ends the other sessions of a password login when licenses are on', function (): void {
    config()->set('core.auth.enable_user_licenses', true);
    Event::fake([OtherDeviceLogout::class]);

    expect(Auth::guard('web')->attempt(['email' => $this->user->email, 'password' => 'secret-password']))->toBeTrue();

    Event::assertDispatched(OtherDeviceLogout::class);
    expect($this->user->fresh()?->password)->not->toBe($this->original_hash);
});

it('ends the other sessions of a locked user too', function (): void {
    config()->set('core.auth.enable_user_licenses', true);
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);
    $this->user->lock();
    Event::fake([OtherDeviceLogout::class]);

    expect(Auth::guard('web')->attempt(['email' => $this->user->email, 'password' => 'secret-password']))->toBeTrue();

    Event::assertDispatched(OtherDeviceLogout::class);
});

it('leaves the other sessions alone when licenses are off', function (): void {
    config()->set('core.auth.enable_user_licenses', false);
    Event::fake([OtherDeviceLogout::class]);

    expect(Auth::guard('web')->attempt(['email' => $this->user->email, 'password' => 'secret-password']))->toBeTrue();

    Event::assertNotDispatched(OtherDeviceLogout::class);
    expect($this->user->fresh()?->password)->toBe($this->original_hash);
});

it('leaves the other sessions alone on a login without a password', function (): void {
    config()->set('core.auth.enable_user_licenses', true);
    Event::fake([OtherDeviceLogout::class]);

    Auth::guard('web')->login($this->user);

    Event::assertNotDispatched(OtherDeviceLogout::class);
    expect($this->user->fresh()?->password)->toBe($this->original_hash);
});

it('leaves the other sessions of a super admin alone', function (): void {
    config()->set('core.auth.enable_user_licenses', true);
    config()->set('permission.roles.superadmin', 'superadmin');
    $root = AppUser::factory()->create(['password' => 'secret-password']);
    $root->assignRole(Role::findOrCreate('superadmin', 'web'));
    $original_hash = $root->fresh()?->password;
    Event::fake([OtherDeviceLogout::class]);

    expect(Auth::guard('web')->attempt(['email' => $root->email, 'password' => 'secret-password']))->toBeTrue();

    Event::assertNotDispatched(OtherDeviceLogout::class);
    expect($root->fresh()?->password)->toBe($original_hash);
});
