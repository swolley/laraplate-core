<?php

declare(strict_types=1);

use Modules\Core\Console\CompactVersions;

test('compact versions command signature contains the command name', function (): void {
    $reflection = new ReflectionClass(CompactVersions::class);
    $signature = $reflection->getProperty('signature');
    $signature->setAccessible(true);
    $instance = $reflection->newInstanceWithoutConstructor();

    expect($signature->getValue($instance))->toContain('versions:compact');
});
