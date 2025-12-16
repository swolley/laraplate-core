<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use Modules\Core\Actions\Users\LeaveImpersonationAction;
use Modules\Core\Events\UserLeftImpersonation;
use Modules\Core\Http\Resources\UserInfoResponse;
use Tests\TestCase;

uses(TestCase::class);

it('leaves impersonation and dispatches event', function (): void {
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

    expect($current->left)->toBeTrue();
    expect($response)->toBeInstanceOf(UserInfoResponse::class);
    Event::assertDispatched(UserLeftImpersonation::class);
});
