<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialUser;
use Modules\Core\Actions\Users\HandleSocialLoginAction;
use Modules\Core\Events\SocialLoginCompleted;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\HandleSocialLoginActionTestUserDouble;
use Symfony\Component\HttpFoundation\Response;

uses(LaravelTestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('uses updateOrCreate and logs in when userUpserter is null', function (): void {
    $socialUser = new class implements SocialUser
    {
        public string $token = 'token';

        public ?string $refreshToken = 'refresh';

        public ?string $tokenSecret = 'secret';

        public function getId()
        {
            return 'social-123';
        }

        public function getNickname()
        {
            return 'nick';
        }

        public function getName()
        {
            return 'Social User';
        }

        public function getEmail()
        {
            return 'social@example.com';
        }

        public function getAvatar()
        {
            return '';
        }
    };

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->once()->andReturn($socialUser);
    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('github')->andReturn($driver);

    $userFromDb = User::factory()->create();
    $queryBuilder = Mockery::mock();
    $queryBuilder->shouldReceive('updateOrCreate')
        ->once()
        ->with(
            Mockery::on(fn (array $where) => ($where['social_id'] ?? null) === 'social-123'),
            Mockery::on(fn (array $attrs) => ($attrs['name'] ?? null) === 'Social User'
                && ($attrs['email'] ?? null) === 'social@example.com'
                && ($attrs['username'] ?? null) === 'nick'
                && ($attrs['social_service'] ?? null) === 'github'
                && ($attrs['social_token'] ?? null) === 'token'
                && ($attrs['social_refresh_token'] ?? null) === 'refresh'
                && ($attrs['social_token_secret'] ?? null) === 'secret'),
        )
        ->andReturn($userFromDb);

    HandleSocialLoginActionTestUserDouble::$queryBuilder = $queryBuilder;
    config(['auth.providers.users.model' => HandleSocialLoginActionTestUserDouble::class]);

    Auth::shouldReceive('hasResolvedGuards')->andReturn(true);
    Auth::shouldReceive('guard')->andReturnSelf();
    Auth::shouldReceive('setDispatcher')->andReturnNull();
    Auth::shouldReceive('login')->once()->with($userFromDb);
    Event::fake();

    $action = new HandleSocialLoginAction(socialite: $socialite);

    $response = $action('github');

    expect($response->getStatusCode())->toBe(Response::HTTP_FOUND);
    expect($response->getTargetUrl())->toContain('/dashboard');
    Event::assertDispatched(SocialLoginCompleted::class, fn ($e) => $e->user === $userFromDb && $e->service === 'github');
});

it('handles social login and dispatches event', function (): void {
    $socialite = Mockery::mock(SocialiteFactory::class);
    $driver = Mockery::mock();
    $socialUser = new class implements SocialUser
    {
        public string $token = 'token';

        public ?string $refreshToken = 'refresh';

        public ?string $tokenSecret = null;

        public function getId()
        {
            return '123';
        }

        public function getNickname()
        {
            return 'nick';
        }

        public function getName()
        {
            return 'name';
        }

        public function getEmail()
        {
            return 'mail@example.com';
        }

        public function getAvatar()
        {
            return '';
        }
    };

    $driver->shouldReceive('user')->once()->andReturn($socialUser);
    $socialite->shouldReceive('driver')->with('github')->andReturn($driver);

    $authUser = new class implements Authenticatable
    {
        public function getAuthIdentifierName()
        {
            return 'id';
        }

        public function getAuthIdentifier()
        {
            return 1;
        }

        public function getAuthPassword()
        {
            return '';
        }

        public function getRememberToken()
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName()
        {
            return 'remember_token';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }
    };

    Auth::shouldReceive('hasResolvedGuards')->andReturn(true);
    Auth::shouldReceive('guard')->andReturnSelf();
    Auth::shouldReceive('setDispatcher')->andReturnNull();
    Auth::shouldReceive('login')->once()->with($authUser);
    Event::fake();

    $action = new HandleSocialLoginAction(
        socialite: $socialite,
        userUpserter: fn (SocialUser $user, string $service) => $authUser,
    );

    $response = $action('github');

    expect($response->getStatusCode())->toBe(302);
    expect($response->getTargetUrl())->toContain('/dashboard');
    Event::assertDispatched(SocialLoginCompleted::class);
});

it('redirect returns redirect response from socialite driver', function (): void {
    $redirectResponse = new RedirectResponse('https://github.com/login/oauth/authorize');
    $driver = Mockery::mock();
    $driver->shouldReceive('redirect')->once()->andReturn($redirectResponse);
    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('google')->once()->andReturn($driver);

    $action = new HandleSocialLoginAction(socialite: $socialite);

    $response = $action->redirect('google');

    expect($response)->toBe($redirectResponse);
});
