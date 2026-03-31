<?php

declare(strict_types=1);

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Modules\Core\Auth\Providers\FortifyCredentialsProvider;
use Modules\Core\Auth\Providers\SocialiteProvider;
use Modules\Core\Models\License;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

class ProviderUserAliasStub extends User
{
    use MustVerifyEmail;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'email_verified_at',
        'password',
        'lang',
        'social_id',
        'social_service',
        'social_token',
        'social_refresh_token',
        'social_token_secret',
        'license_id',
    ];
}

if (! class_exists(App\Models\User::class, false)) {
    class_alias(ProviderUserAliasStub::class, App\Models\User::class);
}

final class FortifyMustVerifyUserStub extends User
{
    use MustVerifyEmail;
}

beforeEach(function (): void {
    if (! Schema::hasColumn('users', 'social_id')) {
        Schema::table('users', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->string('social_id')->nullable();
            $table->string('social_service')->nullable();
            $table->string('social_token')->nullable();
            $table->string('social_refresh_token')->nullable();
            $table->string('social_token_secret')->nullable();
        });
    }
});

it('handles fortify canHandle/isEnabled/provider name branches', function (): void {
    $provider = new FortifyCredentialsProvider();

    expect($provider->canHandle(request()->duplicate(['email' => 'john@example.test', 'password' => 'secret'])))->toBeTrue()
        ->and($provider->canHandle(request()->duplicate(['username' => 'john', 'password' => 'secret'])))->toBeTrue()
        ->and($provider->canHandle(request()->duplicate(['username' => 'john'])))->toBeFalse();

    config(['auth.providers.users.driver' => 'eloquent']);
    expect($provider->isEnabled())->toBeTrue()
        ->and($provider->getProviderName())->toBe('credentials');

    config(['auth.providers.users.driver' => 'database']);
    expect($provider->isEnabled())->toBeFalse();
});

it('authenticates credentials and covers invalid and license branches', function (): void {
    $provider = new FortifyCredentialsProvider();
    $role = Role::factory()->create(['name' => 'member', 'guard_name' => null]);
    $user = ProviderUserAliasStub::query()->create([
        'name' => 'Fortify Member',
        'email' => 'fortify@example.test',
        'username' => 'fortify-user',
        'password' => Hash::make('secret'),
        'license_id' => null,
        'email_verified_at' => now(),
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => ProviderUserAliasStub::class,
        'model_id' => $user->id,
    ]);

    $invalid = $provider->authenticate(request()->duplicate([
        'email' => 'fortify@example.test',
        'password' => 'wrong',
    ]));
    expect($invalid['success'])->toBeFalse()
        ->and($invalid['error'])->toBe('Invalid credentials');

    config(['auth.enable_user_licenses' => false]);
    $success = $provider->authenticate(request()->duplicate([
        'username' => 'fortify-user',
        'password' => 'secret',
    ]));
    expect($success['error'])->toBeNull()
        ->and($success['success'])->toBeTrue()
        ->and($success['user'])->toBeInstanceOf(User::class);

    config(['auth.enable_user_licenses' => true]);
    License::query()->delete();
    $license_error = $provider->authenticate(request()->duplicate([
        'email' => 'fortify@example.test',
        'password' => 'secret',
    ]));
    expect($license_error['success'])->toBeFalse()
        ->and($license_error['error'])->toBe('No free licenses available');
});

it('returns email-not-verified and license-available success branches for fortify', function (): void {
    $provider = new FortifyCredentialsProvider();
    $role = Role::factory()->create(['name' => 'member-verify', 'guard_name' => null]);

    $unverified = ProviderUserAliasStub::query()->create([
        'name' => 'Unverified User',
        'email' => 'unverified@example.test',
        'username' => 'unverified-user',
        'password' => Hash::make('secret'),
        'license_id' => null,
        'email_verified_at' => null,
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => ProviderUserAliasStub::class,
        'model_id' => $unverified->id,
    ]);

    config(['auth.enable_user_licenses' => false]);
    $email_error = $provider->authenticate(request()->duplicate([
        'email' => 'unverified@example.test',
        'password' => 'secret',
    ]));
    expect($email_error['success'])->toBeFalse()
        ->and($email_error['error'])->toBe('Email not verified');

    $verified = ProviderUserAliasStub::query()->create([
        'name' => 'Licensed User',
        'email' => 'licensed@example.test',
        'username' => 'licensed-user',
        'password' => Hash::make('secret'),
        'license_id' => null,
        'email_verified_at' => now(),
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => ProviderUserAliasStub::class,
        'model_id' => $verified->id,
    ]);

    License::factory()->create();
    config(['auth.enable_user_licenses' => true]);
    $licensed_success = $provider->authenticate(request()->duplicate([
        'email' => 'licensed@example.test',
        'password' => 'secret',
    ]));
    expect($licensed_success['success'])->toBeTrue()
        ->and($licensed_success['error'])->toBeNull();
});

it('covers fortify private email verification and license helper methods', function (): void {
    $provider = new FortifyCredentialsProvider();
    $verify_method = new ReflectionMethod(FortifyCredentialsProvider::class, 'shouldVerifyEmail');
    $verify_method->setAccessible(true);

    $check_license_method = new ReflectionMethod(FortifyCredentialsProvider::class, 'checkLicense');
    $check_license_method->setAccessible(true);

    $verify_user = new class extends ProviderUserAliasStub
    {
        use MustVerifyEmail;
    };
    $verify_user->email_verified_at = null;
    $verify_user->setRelation('roles', collect());
    $verify_user->license_id = null;

    config(['auth.enable_user_licenses' => false]);
    expect($verify_method->invoke($provider, $verify_user))->toBeTrue()
        ->and($check_license_method->invoke($provider, $verify_user))->toBeNull();
});

it('handles socialite canHandle and enabled/provider name branches', function (): void {
    $provider = new SocialiteProvider();

    config(['services.socialite.providers' => ['github', 'google']]);
    expect($provider->canHandle(request()->duplicate(['provider' => 'github'])))->toBeTrue()
        ->and($provider->canHandle(request()->duplicate(['provider' => 'unknown'])))->toBeFalse();

    config(['auth.providers.socialite.enabled' => true]);
    expect($provider->isEnabled())->toBeTrue()
        ->and($provider->getProviderName())->toBe('social');

    config(['auth.providers.socialite.enabled' => false]);
    expect($provider->isEnabled())->toBeFalse();
});

it('returns social error when socialite throws', function (): void {
    $provider = new SocialiteProvider();
    Socialite::shouldReceive('driver')
        ->once()
        ->with('github')
        ->andThrow(new Exception('boom'));

    $failed = $provider->authenticate(request()->duplicate(['provider' => 'github']));
    expect($failed['success'])->toBeFalse()
        ->and($failed['error'])->toBe('Social authentication failed');
});

it('returns conflict when email exists with another account type', function (): void {
    $provider = new SocialiteProvider();

    ProviderUserAliasStub::query()->create([
        'name' => 'Taken Social',
        'email' => 'taken-social@example.test',
        'username' => 'taken-social',
        'password' => Hash::make('secret'),
    ]);
    $social_user_conflict = new class
    {
        public string $token = 'token';

        public ?string $refreshToken = 'refresh-token';

        public ?string $tokenSecret = 'secret';

        public function getEmail(): string
        {
            return 'taken-social@example.test';
        }

        public function getId(): string
        {
            return 'social-1';
        }

        public function getName(): string
        {
            return 'Social User';
        }

        public function getNickname(): string
        {
            return 'social_user';
        }
    };
    $driver_mock = Mockery::mock();
    $driver_mock->shouldReceive('user')->once()->andReturn($social_user_conflict);
    Socialite::shouldReceive('driver')->once()->with('github')->andReturn($driver_mock);

    $conflict = $provider->authenticate(request()->duplicate(['provider' => 'github']));
    expect($conflict['success'])->toBeFalse()
        ->and($conflict['error'])->toBe('User already registered with another account type');
});

it('authenticates social user successfully when data is valid', function (): void {
    $provider = new SocialiteProvider();
    $social_user_success = new class
    {
        public string $token = 'token-success';

        public ?string $refreshToken = 'refresh-success';

        public ?string $tokenSecret = 'secret-success';

        public function getEmail(): string
        {
            return 'new-social@example.test';
        }

        public function getId(): string
        {
            return 'social-2';
        }

        public function getName(): string
        {
            return 'New Social';
        }

        public function getNickname(): string
        {
            return 'new_social';
        }
    };
    $driver_mock = Mockery::mock();
    $driver_mock->shouldReceive('user')->once()->andReturn($social_user_success);
    Socialite::shouldReceive('driver')->once()->with('github')->andReturn($driver_mock);

    expect(Schema::hasColumn('users', 'social_id'))->toBeTrue();
    config(['auth.enable_user_licenses' => false]);
    $success = $provider->authenticate(request()->duplicate(['provider' => 'github']));
    expect($success['error'])->toBeNull()
        ->and($success['success'])->toBeTrue()
        ->and($success['user'])->toBeInstanceOf(User::class);
});

it('covers socialite license error and enabled-license success branches', function (): void {
    $provider = new SocialiteProvider();

    $social_user = new class
    {
        public string $token = 'token-social-license';

        public ?string $refreshToken = 'refresh-social-license';

        public ?string $tokenSecret = 'secret-social-license';

        public function getEmail(): string
        {
            return 'licensed-social@example.test';
        }

        public function getId(): string
        {
            return 'social-license-id';
        }

        public function getName(): string
        {
            return 'Licensed Social';
        }

        public function getNickname(): string
        {
            return 'licensed_social';
        }
    };

    $driver_mock = Mockery::mock();
    $driver_mock->shouldReceive('user')->twice()->andReturn($social_user);
    Socialite::shouldReceive('driver')->twice()->with('github')->andReturn($driver_mock);

    License::query()->delete();
    config(['auth.enable_user_licenses' => true]);
    $license_error = $provider->authenticate(request()->duplicate(['provider' => 'github']));
    expect($license_error['success'])->toBeFalse()
        ->and($license_error['error'])->toBe('No free licenses available');

    License::factory()->create();
    $licensed_success = $provider->authenticate(request()->duplicate(['provider' => 'github']));
    expect($licensed_success['success'])->toBeTrue()
        ->and($licensed_success['error'])->toBeNull();
});

it('covers socialite private checkLicense helper', function (): void {
    $provider = new SocialiteProvider();
    $method = new ReflectionMethod(SocialiteProvider::class, 'checkLicense');
    $method->setAccessible(true);

    $role = Role::factory()->create(['name' => 'member-social', 'guard_name' => null]);

    /** @var ProviderUserAliasStub $user */
    $user = ProviderUserAliasStub::query()->create([
        'name' => 'Social Member',
        'username' => 'social-member',
        'email' => 'social-member@example.test',
        'password' => Hash::make('secret'),
        'license_id' => null,
    ]);
    $user->setRelation('roles', collect([$role]));

    config(['auth.enable_user_licenses' => true]);
    License::query()->delete();

    expect($method->invoke($provider, $user))->toBe('No free licenses available');
});
