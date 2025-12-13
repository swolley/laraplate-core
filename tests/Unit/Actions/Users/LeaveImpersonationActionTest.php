<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use Modules\Core\Actions\Users\LeaveImpersonationAction;
use Modules\Core\Events\UserLeftImpersonation;
use Modules\Core\Http\Resources\UserInfoResponse;
use Tests\TestCase;

final class LeaveImpersonationActionTest extends TestCase
{
    public function test_leaves_impersonation_and_dispatches_event(): void
    {
        Event::fake();

        $current = new class extends User
        {
            public bool $left = false;

            public function leaveImpersonation(): void
            {
                $this->left = true;
            }
        };

        $action = new LeaveImpersonationAction();
        $response = $action($current);

        $this->assertTrue($current->left);
        $this->assertInstanceOf(UserInfoResponse::class, $response);
        Event::assertDispatched(UserLeftImpersonation::class);
    }
}

