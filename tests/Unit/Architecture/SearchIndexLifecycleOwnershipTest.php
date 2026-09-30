<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * A search index is created and dropped by Core's search engines and commands, never by a module.
 * A module that wants its records reindexed calls `searchable()` on a scoped query (for extended
 * contents: `Content::withExtended()->where('extended_type', $alias)->searchable()`), which
 * rewrites its own documents and leaves every other module's documents in the shared index alone.
 *
 * The only exception is AI's RAG index, which is AI's own index rather than a model's.
 */
it('leaves index creation and deletion to Core search and the AI RAG index', function (): void {
    $project_root = dirname(__DIR__, 5);
    $allowed = [
        'Core/app/Search/',
        'AI/app/Console/CreateRagElasticsearchIndexCommand.php',
    ];
    $offenders = [];

    foreach ((new Finder)->files()->in($project_root . '/Modules/*/app')->name('*.php') as $file) {
        $path = mb_substr($file->getPathname(), mb_strlen($project_root . '/Modules/'));

        if (array_filter($allowed, static fn (string $prefix): bool => str_starts_with($path, $prefix)) !== []) {
            continue;
        }

        if (preg_match('/(->|::)(createIndex|deleteIndex)\s*\(/', $file->getContents()) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
