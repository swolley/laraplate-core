<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Actions\Users\GetUserInfoAction;
use Modules\Core\Http\Resources\UserInfoResponse;
use Tests\TestCase;

final class GetUserInfoActionTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_returns_user_info_and_checks_license(): void
    {
        \Mockery::mock('alias:Modules\Core\Listeners\AfterLoginListener')
            ->shouldReceive('checkUserLicense')
            ->once();

        $user = new class extends User {};

        $action = new GetUserInfoAction();
        $response = $action($user);

        $this->assertInstanceOf(UserInfoResponse::class, $response);
    }

    public function test_returns_anonymous_when_no_user(): void
    {
        $action = new GetUserInfoAction();

        $response = $action(null);

        $this->assertInstanceOf(UserInfoResponse::class, $response);
    }
}

