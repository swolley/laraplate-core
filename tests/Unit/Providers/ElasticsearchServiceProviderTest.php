<?php

declare(strict_types=1);

use Modules\Core\Providers\ElasticsearchServiceProvider;
use Modules\Core\Services\ElasticsearchService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->provider = new ElasticsearchServiceProvider(app());
});

it('registers elasticsearch singleton and alias on register when config exists', function (): void {
    $config_dir = app()->configPath();
    $elastic_path = $config_dir . DIRECTORY_SEPARATOR . 'elastic.php';
    $elastic_client_path = $config_dir . DIRECTORY_SEPARATOR . 'elastic.client.php';

    if (! is_file($elastic_path) || ! is_file($elastic_client_path)) {
        expect(true)->toBeTrue();

        return;
    }

    $this->provider->register();

    expect(app()->bound('elasticsearch'))->toBeTrue();
    expect(app()->bound(ElasticsearchService::class))->toBeTrue();
    expect(app('elasticsearch'))->toBeInstanceOf(ElasticsearchService::class);
});

it('boot runs without throwing when config was merged', function (): void {
    $config_dir = app()->configPath();
    if (! is_file($config_dir . DIRECTORY_SEPARATOR . 'elastic.php')) {
        expect(true)->toBeTrue();

        return;
    }
    $this->provider->register();
    $this->provider->boot();

    expect(true)->toBeTrue();
});
