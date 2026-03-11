<?php

declare(strict_types=1);

use Modules\Core\Services\VersioningService;
use Modules\Core\Tests\TestCase;

uses(TestCase::class);

it('can be instantiated', function (): void {
    $service = new VersioningService();

    expect($service)->toBeInstanceOf(VersioningService::class);
});

