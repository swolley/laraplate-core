<?php

declare(strict_types=1);

/**
 * The versioning library was absorbed into Core (D14). This guards against a new reference to it. A symbol
 * matches only as a whole name: the library's `Version` is not its `Versionable`.
 */
it('leaves no reference to the absorbed versioning library', function (string $symbol): void {
    $root = dirname(__DIR__, 5);
    $offenders = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . DIRECTORY_SEPARATOR . 'Modules', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        $path = $file->getPathname();

        if ($file->getExtension() !== 'php' || $path === __FILE__ || str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            continue;
        }

        if (preg_match('/' . preg_quote($symbol, '/') . '(?![A-Za-z0-9_])/', (string) file_get_contents($path)) === 1) {
            $offenders[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        }
    }

    sort($offenders);

    expect($offenders)->toBe([]);
})->with([
    'library' => ['Overtrue\LaravelVersionable'],
]);
