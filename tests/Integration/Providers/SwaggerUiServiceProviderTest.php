<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Core\Providers\SwaggerUiServiceProvider;


beforeEach(function (): void {
    $this->provider = new SwaggerUiServiceProvider(app());
});

it('registers viewSwaggerUI gate in boot', function (): void {
    $this->provider->boot();

    expect(Gate::getPolicyFor(null))->toBeNull();
    expect(Gate::has('viewSwaggerUI'))->toBeTrue();
});

it('viewSwaggerUI gate denies when user is null', function (): void {
    $this->provider->boot();

    expect(Gate::forUser(null)->allows('viewSwaggerUI'))->toBeFalse();
});

it('viewSwaggerUI gate denies when user email is not in allowed list', function (): void {
    $this->provider->boot();

    $user = Modules\Core\Models\User::factory()->create(['email' => 'any@example.com']);

    expect(Gate::forUser($user)->allows('viewSwaggerUI'))->toBeFalse();
});
