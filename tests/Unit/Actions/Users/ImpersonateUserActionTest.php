<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use Modules\Core\Actions\Users\ImpersonateUserAction;
use Modules\Core\Events\UserImpersonated;
use Modules\Core\Http\Resources\UserInfoResponse;
use Tests\TestCase;

uses(TestCase::class);

it('impersonates and dispatches event', function (): void {
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

    expect($current->impersonated)->toBeTrue();
    expect($response)->toBeInstanceOf(UserInfoResponse::class);
    Event::assertDispatched(UserImpersonated::class);
});
