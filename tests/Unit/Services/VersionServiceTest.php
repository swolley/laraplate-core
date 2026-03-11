<?php

declare(strict_types=1);

use Modules\Core\Services\Docs\VersionService;
use Modules\Core\Tests\TestCase;

uses(TestCase::class);

it('returns null commit hash and tag when git directory does not exist', function (): void {
    $service = new VersionService(__DIR__ . '/non-existent-base');

    expect($service->getCurrentCommitHash())->toBeNull()
        ->and($service->getCurrentTag())->toBeNull();
})
;

it('returns null commit hash when HEAD file is missing', function (): void {
    $base = sys_get_temp_dir() . '/vs-head-missing-' . bin2hex(random_bytes(4));
    mkdir($base . '/.git', 0777, true);

    $service = new VersionService($base);

    expect($service->getCurrentCommitHash())->toBeNull();
});

it('parses detached HEAD hash', function (): void {
    $base = sys_get_temp_dir() . '/vs-detached-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git, 0777, true);

    $hash = str_repeat('a', 40);
    file_put_contents($git . '/HEAD', $hash);

    $service = new VersionService($base);

    expect($service->getCurrentCommitHash())->toBe($hash);
});

