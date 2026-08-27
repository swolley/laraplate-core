<?php

declare(strict_types=1);

/**
 * Long-lived workers share one process across requests. Process-wide primitives
 * (subprocess pools, forks, POSIX signals) must stay out of code a request can
 * reach, both because they are unsafe there and because each subprocess pays a
 * full framework boot.
 */
use Symfony\Component\Finder\Finder;

/**
 * Sources reachable from an HTTP request, excluding the CLI-only allow-list.
 *
 * @return array<string, string>
 */
function http_reachable_sources(): array
{
    // The Unit suite boots a minimal container without a base path, so resolve the
    // project root from this file, exactly as ModelFinalClassTest.php in this
    // directory already does.
    $project_root = dirname(__DIR__, 5);

    $roots = [
        $project_root . '/app',
        $project_root . '/Modules/Core/app',
        $project_root . '/Modules/AI/app',
        $project_root . '/Modules/CMS/app',
        $project_root . '/Modules/ERP/app',
        $project_root . '/Modules/MES/app',
        $project_root . '/Modules/SAO/app',
    ];

    foreach ($roots as $root) {
        // Fail loudly if a module moves: a silently skipped root would narrow the guardrail.
        expect(is_dir($root))->toBeTrue("Expected source root at {$root}");
    }

    $finder = Finder::create()
        ->files()
        ->name('*.php')
        ->in($roots)
        ->notPath('Concurrency')
        ->notPath('Console')
        ->notPath('Performance')
        ->notPath('Helpers/BatchSeeder.php')
        ->notPath('Overrides/Seeder.php');

    $sources = [];

    foreach ($finder as $file) {
        $sources[$file->getRelativePathname()] = (string) $file->getContents();
    }

    return $sources;
}

it('never runs subprocess or fork concurrency in request-reachable code', function (): void {
    $offenders = [];

    foreach (http_reachable_sources() as $path => $contents) {
        if (preg_match("/Concurrency::driver\(/", $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('never installs POSIX signal handlers in request-reachable code', function (): void {
    $offenders = [];

    foreach (http_reachable_sources() as $path => $contents) {
        if (preg_match('/\bpcntl_(signal|alarm|async_signals|fork)\s*\(/', $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
