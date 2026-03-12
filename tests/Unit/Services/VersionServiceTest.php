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

it('resolves commit hash from branch ref', function (): void {
    $base = sys_get_temp_dir() . '/vs-branch-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git . '/refs/heads', 0777, true);

    $hash = str_repeat('b', 40);
    file_put_contents($git . '/HEAD', "ref: refs/heads/main\n");
    file_put_contents($git . '/refs/heads/main', $hash);

    $service = new VersionService($base);

    expect($service->getCurrentCommitHash())->toBe($hash);
});

it('returns highest semantic version tag when no tag matches commit', function (): void {
    $base = sys_get_temp_dir() . '/vs-tags-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git . '/refs/heads', 0777, true);
    mkdir($git . '/refs/tags', 0777, true);

    $hash = str_repeat('c', 40);
    file_put_contents($git . '/HEAD', $hash);

    // Two tags with different semantic versions, pointing to unrelated commits
    file_put_contents($git . '/refs/tags/v1.0.0', str_repeat('d', 40));
    file_put_contents($git . '/refs/tags/v1.2.3', str_repeat('e', 40));

    $service = new VersionService($base);

    expect($service->getCurrentTag())->toBe('v1.2.3');
});

it('returns formatted commit date when object exists and is parsable', function (): void {
    $base = sys_get_temp_dir() . '/vs-date-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git . '/objects/ab', 0777, true);

    $hash = 'ab' . str_repeat('1', 38);
    $timestamp = 1_600_000_000;
    $content = "commit 123\0tree ...\nparent ...\ncommitter Test <t@example.com> {$timestamp} +0000\n\nMessage\n";
    $compressed = gzcompress($content);
    file_put_contents($git . '/objects/ab/' . mb_substr($hash, 2), $compressed);

    $service = new VersionService($base);

    $date = $service->getCommitDate($hash);
    expect($date)->toBe(date('Y-m-d H:i:s', $timestamp));
});


