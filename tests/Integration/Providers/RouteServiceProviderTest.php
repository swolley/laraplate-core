<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Providers\RouteServiceProvider;


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

it('versions limiter returns 120 per minute', function (): void {
    $this->provider->boot();

    $limiter = RateLimiter::limiter('versions');
    $result = $limiter(new stdClass());

    expect($result)->toBeInstanceOf(Illuminate\Cache\RateLimiting\Limit::class);
});

it('translations limiter returns 30 per minute', function (): void {
    $this->provider->boot();

    $limiter = RateLimiter::limiter('translations');
    $result = $limiter(new stdClass());

    expect($result)->toBeInstanceOf(Illuminate\Cache\RateLimiting\Limit::class);
});

it('embeddings limiter returns 10 per minute', function (): void {
    $this->provider->boot();

    $limiter = RateLimiter::limiter('embeddings');
    $result = $limiter();

    expect($result)->toBeInstanceOf(Illuminate\Cache\RateLimiting\Limit::class)
        ->and($result->maxAttempts)->toBe(10);
});

it('versions limiter caps at 120 jobs per minute', function (): void {
    $this->provider->boot();

    $result = RateLimiter::limiter('versions')(new stdClass());

    expect($result->maxAttempts)->toBe(120);
});

it('translations limiter caps at 30 jobs per minute', function (): void {
    $this->provider->boot();

    $result = RateLimiter::limiter('translations')(new stdClass());

    expect($result->maxAttempts)->toBe(30);
});

it('indexing limiter uses production throughput in production environment', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $this->provider->boot();

    $limits = RateLimiter::limiter('indexing')();

    expect($limits)->toBeArray()
        ->and(collect($limits)->every(fn ($limit): bool => $limit instanceof Illuminate\Cache\RateLimiting\Limit))->toBeTrue();
});
