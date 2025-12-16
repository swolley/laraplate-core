<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Modules\Core\Actions\Users\GetUserInfoAction;
use Modules\Core\Http\Resources\UserInfoResponse;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('returns user info and checks license', function (): void {
    Mockery::mock('alias:Modules\Core\Listeners\AfterLoginListener')
        ->shouldReceive('checkUserLicense')
        ->once();

    $user = new class extends User {};

    $action = new GetUserInfoAction();
    $response = $action($user);

    expect($response)->toBeInstanceOf(UserInfoResponse::class);
});

it('returns anonymous when no user', function (): void {
    $action = new GetUserInfoAction();

    $response = $action(null);

    expect($response)->toBeInstanceOf(UserInfoResponse::class);
});
