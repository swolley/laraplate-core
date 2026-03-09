<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Providers\RouteServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->provider = new RouteServiceProvider(app());
});

it('boot registers versions rate limiter', function (): void {
    $this->provider->boot();

    $limiter = RateLimiter::limiter('versions');
    expect($limiter)->toBeCallable();
});

it('boot registers translations rate limiter', function (): void {
    $this->provider->boot();

    expect(RateLimiter::limiter('translations'))->toBeCallable();
});

it('boot registers embeddings rate limiter', function (): void {
    $this->provider->boot();

    expect(RateLimiter::limiter('embeddings'))->toBeCallable();
});

it('boot registers indexing rate limiter', function (): void {
    $this->provider->boot();

    expect(RateLimiter::limiter('indexing'))->toBeCallable();
});
