<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialUser;
use Modules\Core\Actions\Users\HandleSocialLoginAction;
use Modules\Core\Events\SocialLoginCompleted;
use Tests\TestCase;

final class HandleSocialLoginActionTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_handles_social_login_and_dispatches_event(): void
    {
        $socialite = \Mockery::mock(SocialiteFactory::class);
        $driver = \Mockery::mock();
        $socialUser = new class implements SocialUser
        {
            public string $token = 'token';
            public ?string $refreshToken = 'refresh';
            public ?string $tokenSecret = null;

            public function getId() { return '123'; }
            public function getNickname() { return 'nick'; }
            public function getName() { return 'name'; }
            public function getEmail() { return 'mail@example.com'; }
            public function getAvatar() { return ''; }
        };

        $driver->shouldReceive('user')->once()->andReturn($socialUser);
        $socialite->shouldReceive('driver')->with('github')->andReturn($driver);

        $authUser = new class implements Authenticatable
        {
            public function getAuthIdentifierName() { return 'id'; }
            public function getAuthIdentifier() { return 1; }
            public function getAuthPassword() { return ''; }
            public function getRememberToken() { return null; }
            public function setRememberToken($value): void {}
            public function getRememberTokenName() { return 'remember_token'; }
            public function getAuthPasswordName(): string { return 'password'; }
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

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/dashboard', $response->getTargetUrl());
        Event::assertDispatched(SocialLoginCompleted::class);
    }
}

