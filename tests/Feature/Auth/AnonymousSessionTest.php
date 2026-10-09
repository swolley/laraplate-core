<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\User;

/*
 * An unauthenticated `/app` call is checked as the anonymous user, who is never signed in to the web session: the
 * anonymous account is resolved for the permission check of that request only. A session sign-in would let the
 * next request of the same browser through the `auth` group, where it could write the shared anonymous row.
 */

beforeEach(function (): void {
    Cache::flush();
    $this->anonymous = User::factory()->create([
        'name' => config('permission.users.guest'),
        'username' => config('permission.users.guest'),
        'email' => 'anonymous_session_' . uniqid() . '@example.com',
    ]);
});

it('checks an unauthenticated app call as the anonymous user without signing it in to the session', function (): void {
    $this->getJson(route('core.crud.list', ['module' => 'core', 'entity' => 'settings']))->assertUnauthorized();

    expect(session()->has(Auth::guard('web')->getName()))->toBeFalse();
});

it('refuses the auth routes to the next request of the same session', function (): void {
    $this->getJson(route('core.crud.list', ['module' => 'core', 'entity' => 'settings']));
    $session_id = session()->getId();
    Auth::forgetGuards();

    $this->withCookie((string) config('session.cookie'), $session_id)
        ->patchJson(route('core.auth.updatePreferences'), ['preferences' => ['ui' => ['theme' => 'dark']]])
        ->assertUnauthorized();

    expect($this->anonymous->fresh()->preferences ?? null)->toBeEmpty();
});
