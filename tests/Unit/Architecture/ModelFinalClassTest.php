<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Eloquent models that must stay non-final because the host application or tests extend them.
 *
 * @var list<string>
 */
const EXTENDABLE_MODEL_FILENAMES = [
    'User.php',
    'Place.php',
];

it('keeps extendable module models non-final', function (): void {
    $project_root = dirname(__DIR__, 5);

    foreach (EXTENDABLE_MODEL_FILENAMES as $filename) {
        $path = $project_root . '/Modules/Core/app/Models/' . $filename;

        expect(file_exists($path))->toBeTrue("Expected extendable model at {$path}");

        $contents = file_get_contents($path);

        expect($contents)->not->toMatch('/\bfinal\s+class\s+/i');
    }
});

it('does not mark extendable module models as final in the models tree', function (): void {
    $project_root = dirname(__DIR__, 5);

    $finder = Finder::create()
        ->files()
        ->in($project_root . '/Modules/Core/app/Models')
        ->name(EXTENDABLE_MODEL_FILENAMES);

    expect(iterator_count($finder))->toBe(count(EXTENDABLE_MODEL_FILENAMES));

    foreach ($finder as $file) {
        expect($file->getContents())->not->toMatch('/\bfinal\s+class\s+/i');
    }
});
