<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('references nothing from the laravel-approval package', function (): void {
    $offenders = [];
    $project_root = dirname(__DIR__, 5);

    foreach ((new Finder)->files()->in($project_root . '/Modules')->name('*.php')->exclude(['vendor', 'node_modules']) as $file) {
        if (preg_match('/\bApproval\\\\(Models|Traits)\\\\/', $file->getContents()) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
