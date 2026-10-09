<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Tests\Stubs\Models\HidesAttributesStub;

/*
 * A model that declares personal attributes loses them when it is serialized for the anonymous user, and only then:
 * with no user acting (console, queue, search indexing) and for any authenticated user it serializes as it always did.
 */

function hidesattrs_user(?string $email, string $name): User
{
    return (new User)->forceFill(['name' => $name, 'username' => $name, 'email' => $email]);
}

function hidesattrs_stub(): HidesAttributesStub
{
    return new HidesAttributesStub(['title' => 'a title', 'user_id' => 42]);
}

it('hides the declared attributes from the anonymous user', function (): void {
    Auth::setUser(hidesattrs_user(null, config('permission.users.guest')));

    expect(hidesattrs_stub()->toArray())->toBe(['title' => 'a title']);
});

it('hides them from the account named as the guest even when it has an email', function (): void {
    Auth::setUser(hidesattrs_user('guest@example.test', config('permission.users.guest')));

    expect(hidesattrs_stub()->toArray())->not->toHaveKey('user_id');
});

it('keeps them for an authenticated user', function (): void {
    Auth::setUser(hidesattrs_user('staff@example.test', 'staff'));

    expect(hidesattrs_stub()->toArray())->toBe(['title' => 'a title', 'user_id' => 42]);
});

it('keeps them when no user acts', function (): void {
    expect(hidesattrs_stub()->toArray())->toBe(['title' => 'a title', 'user_id' => 42]);
});

it('does not carry the hiding over to a later serialization of the same instance', function (): void {
    $stub = hidesattrs_stub();

    Auth::setUser(hidesattrs_user(null, config('permission.users.guest')));
    expect($stub->toArray())->not->toHaveKey('user_id');

    Auth::setUser(hidesattrs_user('staff@example.test', 'staff'));
    expect($stub->toArray())->toHaveKey('user_id');
});
