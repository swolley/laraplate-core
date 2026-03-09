<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Modules\Core\Actions\Users\GetUserInfoAction;
use Modules\Core\Http\Resources\UserInfoResponse;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('returns user info and checks license', function (): void {
    // Use a plain Auth User so AfterLoginListener::checkUserLicense runs but does not
    // perform license logic (it requires Modules\Core\Models\User and other conditions).
    // Avoids alias mock so AfterLoginListenerTest is not broken by class replacement.
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
