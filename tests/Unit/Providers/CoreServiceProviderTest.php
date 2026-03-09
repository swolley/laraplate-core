<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Modules\Core\Providers\CoreServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->provider = new CoreServiceProvider(app());
});

it('registerAuths runs without throwing', function (): void {
    $this->provider->registerAuths();

    expect(true)->toBeTrue();
});

it('configureDates uses CarbonImmutable', function (): void {
    $this->provider->register();
    $ref = new \ReflectionClass($this->provider);
    $method = $ref->getMethod('configureDates');
    $method->setAccessible(true);
    $method->invoke($this->provider);

    expect(Date::getTestNow())->toBeNull();
    $instance = Date::now();
    expect($instance)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});
