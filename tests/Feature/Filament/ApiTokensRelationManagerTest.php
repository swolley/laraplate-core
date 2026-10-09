<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\CreateUser;
use Modules\Core\Filament\Resources\Users\Pages\EditUser;
use Modules\Core\Filament\Resources\Users\RelationManagers\ApiTokensRelationManager;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

const API_TOKENS_RM_PERMISSION = 'default.users.select';

beforeEach(function (): void {
    config()->set('permission.roles.superadmin', 'superadmin');

    /** @var App\Models\User $admin */
    $admin = App\Models\User::query()->findOrFail(User::factory()->create()->getKey());
    $admin->assignRole(Role::findOrCreate('superadmin', 'web'));

    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');
});

/**
 * An account holding the select permission of the users entity through an api role.
 */
function apiTokensRmAccount(bool $serviceAccount): App\Models\User
{
    foreach (['web', 'api'] as $guard) {
        Permission::query()->firstOrCreate(['name' => API_TOKENS_RM_PERMISSION, 'guard_name' => $guard]);
    }

    $role = Role::factory()->create(['name' => 'rm_role_' . uniqid(), 'guard_name' => 'api']);
    $role->givePermissionTo(Permission::query()->where(['name' => API_TOKENS_RM_PERMISSION, 'guard_name' => 'api'])->firstOrFail());

    $factory = $serviceAccount ? User::factory()->serviceAccount() : User::factory();
    $account = App\Models\User::query()->findOrFail($factory->create()->getKey());
    $account->assignRole($role);

    return $account->fresh();
}

function apiTokensRmComponent(App\Models\User $owner): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(ApiTokensRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditUser::class]);
}

it('issues a non-expiring token for a service account and shows the plain token once', function (): void {
    $service = apiTokensRmAccount(serviceAccount: true);

    $component = apiTokensRmComponent($service)
        ->callTableAction('issue', null, [
            'name' => 'edge agent',
            'abilities' => [API_TOKENS_RM_PERMISSION],
            'expires_at' => null,
            'allowed_cidrs' => ['10.0.0.0/8'],
        ])
        ->assertHasNoTableActionErrors()
        ->assertActionMounted('revealToken');

    $stored = PersonalAccessToken::query()->sole();

    expect($stored->name)->toBe('edge agent')
        ->and($stored->expires_at)->toBeNull()
        ->and($stored->abilities)->toBe([API_TOKENS_RM_PERMISSION])
        ->and(json_decode((string) $stored->getRawOriginal('allowed_cidrs'), true))->toBe(['10.0.0.0/8'])
        ->and($stored->tokenable_type)->toBe(App\Models\User::class);

    $plain = data_get($component->instance()->mountedActions, '0.arguments.token');

    expect($plain)->toBeString()
        ->and(PersonalAccessToken::findToken($plain)?->is($stored))->toBeTrue()
        ->and(session()->all())->not->toContain($plain)
        ->and(json_encode(session()->all()))->not->toContain(explode('|', $plain, 2)[1]);
});

it('refuses to issue a token with an ability the account does not hold', function (): void {
    $service = apiTokensRmAccount(serviceAccount: true);
    Permission::query()->firstOrCreate(['name' => 'default.roles.delete', 'guard_name' => 'api']);

    apiTokensRmComponent($service)
        ->callTableAction('issue', null, ['name' => 'x', 'abilities' => ['default.roles.delete']])
        ->assertHasTableActionErrors(['abilities.0']);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('offers only the api permissions the account holds', function (): void {
    $service = apiTokensRmAccount(serviceAccount: true);
    Permission::query()->firstOrCreate(['name' => 'default.roles.delete', 'guard_name' => 'api']);

    $options = collect(app(Modules\Core\Services\ApiTokenService::class)->grantableAbilities($service));

    expect($options->all())->toBe([API_TOKENS_RM_PERMISSION]);
});

it('does not offer to issue a token for an ordinary account', function (): void {
    apiTokensRmComponent(apiTokensRmAccount(serviceAccount: false))->assertTableActionHidden('issue');
    apiTokensRmComponent(apiTokensRmAccount(serviceAccount: true))->assertTableActionVisible('issue');
});

it('lists the tokens with last use and expiry and revokes one', function (): void {
    $service = apiTokensRmAccount(serviceAccount: true);
    $new = $service->createToken('agent', [API_TOKENS_RM_PERMISSION]);
    $new->accessToken->forceFill(['last_used_at' => now()->subMinutes(5)])->save();
    $token = $new->accessToken;

    apiTokensRmComponent($service)
        ->assertCanSeeTableRecords([$token])
        ->assertTableColumnStateSet('abilities', [API_TOKENS_RM_PERMISSION], $token)
        ->assertTableColumnStateSet('expires_at', null, $token)
        ->callTableAction('revoke', $token);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('hides the token list from a user who cannot edit the account', function (): void {
    $service = apiTokensRmAccount(serviceAccount: true);
    $viewer = App\Models\User::query()->findOrFail(User::factory()->create()->getKey());

    $this->actingAs($viewer);

    expect(ApiTokensRelationManager::canViewForRecord($service, EditUser::class))->toBeFalse();
});

it('gives a service account created in the backoffice a random password it cannot be told', function (): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Edge agent',
            'username' => 'edge-agent',
            'email' => 'edge-agent@example.test',
            'is_service_account' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = App\Models\User::query()->where('username', 'edge-agent')->firstOrFail();

    expect($created->isServiceAccount())->toBeTrue()
        ->and($created->getAuthPassword())->not->toBe('')
        ->and(Hash::check('', $created->getAuthPassword()))->toBeFalse();
});

it('replaces the password of an account turned into a service account', function (): void {
    $user = App\Models\User::query()->findOrFail(User::factory()->create(['password' => 'Password1!'])->getKey());
    $before = $user->getAuthPassword();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['is_service_account' => true, 'lang' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->isServiceAccount())->toBeTrue()
        ->and($user->getAuthPassword())->not->toBe($before)
        ->and(Hash::check('Password1!', $user->getAuthPassword()))->toBeFalse();
});
