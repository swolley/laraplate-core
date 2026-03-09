<?php

declare(strict_types=1);

use Modules\Core\Overrides\ServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('can be instantiated with app and has name properties', function (): void {
    $provider = new class(app()) extends ServiceProvider {
        public string $name = 'Test';

        public string $nameLower = 'test';

        public function __construct($app)
        {
            parent::__construct($app);
            $this->name = 'Test';
            $this->nameLower = 'test';
        }
    };

    expect($provider)->toBeInstanceOf(ServiceProvider::class);
    expect($provider->name)->toBe('Test');
    expect($provider->nameLower)->toBe('test');
});
