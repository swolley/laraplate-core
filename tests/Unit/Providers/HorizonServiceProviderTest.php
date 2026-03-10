<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Providers\HorizonServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->provider = new HorizonServiceProvider(app());
});

it('defines viewHorizon gate when gate is called', function (): void {
    $ref = new ReflectionClass($this->provider);
    $method = $ref->getMethod('gate');
    $method->invoke($this->provider);

    expect(Gate::has('viewHorizon'))->toBeTrue();
});

it('viewHorizon allows superadmin when user is App\Models\User', function (): void {
    if (! class_exists(\App\Models\User::class)) {
        class_alias(User::class, \App\Models\User::class);
    }

    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'superadmin']);
    $user->roles()->attach($role);

    $ref = new ReflectionClass($this->provider);
    $method = $ref->getMethod('gate');
    $method->invoke($this->provider);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue();
});

it('viewHorizon denies null user', function (): void {
    $ref = new ReflectionClass($this->provider);
    $method = $ref->getMethod('gate');
    $method->invoke($this->provider);

    expect(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
});

it('viewHorizon denies non superadmin user', function (): void {
    $user = User::factory()->create();

    $ref = new ReflectionClass($this->provider);
    $method = $ref->getMethod('gate');
    $method->invoke($this->provider);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});
