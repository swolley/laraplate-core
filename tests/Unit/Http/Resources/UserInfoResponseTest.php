<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Core\Http\Resources\UserInfoResponse;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('transforms null resource to anonymous array', function (): void {
    $resource = new UserInfoResponse(null);
    $array = $resource->toArray(new Request);

    expect($array['id'])->toBe('anonymous')
        ->and($array['name'])->toBe('anonymous')
        ->and($array['permissions'])->toBe([])
        ->and($array['groups'])->toBe([]);
});

it('transforms user resource to array with permissions and groups', function (): void {
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'editor']);
    $user->roles()->attach($role);

    $resource = new UserInfoResponse($user);
    $array = $resource->toArray(new Request);

    expect($array['id'])->toBe($user->id)
        ->and($array['username'])->toBe($user->username)
        ->and($array['groups'])->toContain('editor')
        ->and($array)->toHaveKey('permissions');
});

it('includes canImpersonate in array', function (): void {
    $user = User::factory()->create();
    $resource = new UserInfoResponse($user);
    $array = $resource->toArray(new Request);

    expect($array)->toHaveKey('canImpersonate');
});
