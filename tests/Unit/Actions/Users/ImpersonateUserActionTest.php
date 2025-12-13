<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use Modules\Core\Actions\Users\ImpersonateUserAction;
use Modules\Core\Events\UserImpersonated;
use Modules\Core\Http\Resources\UserInfoResponse;
use Tests\TestCase;

final class ImpersonateUserActionTest extends TestCase
{
    public function test_impersonates_and_dispatches_event(): void
    {
        Event::fake();

        $current = new class extends User
        {
            public bool $impersonated = false;

            public function impersonate($user): void
            {
                $this->impersonated = true;
            }
        };

        $target = new class extends User {};

        $action = new ImpersonateUserAction();
        $response = $action($current, $target);

        $this->assertTrue($current->impersonated);
        $this->assertInstanceOf(UserInfoResponse::class, $response);
        Event::assertDispatched(UserImpersonated::class);
    }
}

