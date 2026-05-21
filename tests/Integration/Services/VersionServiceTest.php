<?php

declare(strict_types=1);

use Modules\Core\Services\Docs\VersionService;


it('returns null commit hash and tag when git directory does not exist', function (): void {
    $service = new VersionService(__DIR__ . '/non-existent-base');

    expect($service->getCurrentCommitHash())->toBeNull()
        ->and($service->getCurrentTag())->toBeNull();
});

it('returns null commit hash when HEAD file is missing', function (): void {
    $base = sys_get_temp_dir() . '/vs-head-missing-' . bin2hex(random_bytes(4));
    mkdir($base . '/.git', 0777, true);

    $service = new VersionService($base);

    expect($service->getCurrentCommitHash())->toBeNull();
});

it('returns null commit hash when HEAD references missing branch file', function (): void {
    $base = sys_get_temp_dir() . '/vs-missing-branch-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git . '/refs/heads', 0777, true);
    file_put_contents($git . '/HEAD', "ref: refs/heads/not-found\n");

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

it('returns null tag when commit hash cannot be resolved', function (): void {
    $base = sys_get_temp_dir() . '/vs-no-commit-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git . '/refs/tags', 0777, true);
    file_put_contents($git . '/HEAD', "ref: refs/heads/main\n");

    $service = new VersionService($base);

    expect($service->getCurrentTag())->toBeNull();
});

it('returns null tag when tags directory does not exist', function (): void {
    $base = sys_get_temp_dir() . '/vs-no-tags-dir-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git, 0777, true);
    file_put_contents($git . '/HEAD', str_repeat('a', 40));

    $service = new VersionService($base);

    expect($service->getCurrentTag())->toBeNull();
});

it('skips non-file tags and resolves tag from git object payload', function (): void {
    $base = sys_get_temp_dir() . '/vs-object-tag-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git . '/refs/tags/not-a-file', 0777, true);
    mkdir($git . '/objects/cc', 0777, true);

    $currentCommit = str_repeat('c', 40);
    $tagCommit = 'cc' . str_repeat('1', 38);
    file_put_contents($git . '/HEAD', $currentCommit);
    file_put_contents($git . '/refs/tags/v3.0.0', $tagCommit);
    file_put_contents($git . '/objects/cc/' . mb_substr($tagCommit, 2), 'header ' . $currentCommit . ' footer');

    $service = new VersionService($base);

    expect($service->getCurrentTag())->toBe('v3.0.0');
});

it('returns direct tag when current commit matches tag commit', function (): void {
    $base = sys_get_temp_dir() . '/vs-direct-tag-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git . '/refs/tags', 0777, true);

    $hash = str_repeat('f', 40);
    file_put_contents($git . '/HEAD', $hash);
    file_put_contents($git . '/refs/tags/v2.0.0', $hash);

    $service = new VersionService($base);

    expect($service->getCurrentTag())->toBe('v2.0.0');
});

it('getCurrentPackageVersion falls back to version helper when no tag is available', function (): void {
    $service = new VersionService(__DIR__ . '/non-existent-base');

    expect($service->getCurrentPackageVersion())->toBe(version());
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

it('getCommitDate returns null for null hash', function (): void {
    $service = new VersionService(__DIR__ . '/non-existent-base');

    expect($service->getCommitDate(null))->toBeNull();
});

it('getCommitDate returns null when git directory does not exist for non-null hash', function (): void {
    $service = new VersionService(__DIR__ . '/non-existent-base');

    expect($service->getCommitDate(str_repeat('a', 40)))->toBeNull();
});

it('getCommitDate returns null when object file does not exist', function (): void {
    $base = sys_get_temp_dir() . '/vs-date-missing-object-' . bin2hex(random_bytes(4));
    mkdir($base . '/.git', 0777, true);
    $service = new VersionService($base);

    expect($service->getCommitDate('ab' . str_repeat('2', 38)))->toBeNull();
});

it('getCommitDate returns null when object file exists but is not readable', function (): void {
    $base = sys_get_temp_dir() . '/vs-date-read-fail-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    $hash = 'aa' . str_repeat('5', 38);
    mkdir($git . '/objects/aa', 0777, true);
    $objectPath = $git . '/objects/aa/' . mb_substr($hash, 2);
    file_put_contents($objectPath, 'content');
    chmod($objectPath, 0000);

    set_error_handler(static fn () => true);

    try {
        $service = new VersionService($base);
        expect($service->getCommitDate($hash))->toBeNull();
    } finally {
        chmod($objectPath, 0644);
        restore_error_handler();
    }
});

it('getCommitDate returns null when commit object cannot be uncompressed', function (): void {
    $base = sys_get_temp_dir() . '/vs-date-uncompress-fail-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    $hash = 'bb' . str_repeat('6', 38);
    mkdir($git . '/objects/bb', 0777, true);
    file_put_contents($git . '/objects/bb/' . mb_substr($hash, 2), 'not-a-zlib-payload');

    set_error_handler(static fn () => true);

    try {
        $service = new VersionService($base);
        expect($service->getCommitDate($hash))->toBeNull();
    } finally {
        restore_error_handler();
    }
});

it('getCommitDate returns null when committer pattern is missing', function (): void {
    $base = sys_get_temp_dir() . '/vs-date-no-committer-' . bin2hex(random_bytes(4));
    $git = $base . '/.git';
    mkdir($git . '/objects/ef', 0777, true);
    $hash = 'ef' . str_repeat('4', 38);
    $compressed = gzcompress("commit 123\0tree ...\nno-committer-line\n");
    file_put_contents($git . '/objects/ef/' . mb_substr($hash, 2), $compressed);

    $service = new VersionService($base);

    expect($service->getCommitDate($hash))->toBeNull();
});
