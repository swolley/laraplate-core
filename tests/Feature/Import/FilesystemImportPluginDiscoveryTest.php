<?php

declare(strict_types=1);

use Modules\Core\Import\Contracts\BulkImporterInterface;
use Modules\Core\Import\Support\FilesystemImportPluginDiscovery;
use Modules\Core\Tests\Fixtures\ImportPlugin\AbstractFixtureImporter;
use Modules\Core\Tests\Fixtures\ImportPlugin\ConcreteFixtureImporter;
use Modules\Core\Tests\Fixtures\ImportPlugin\UnrelatedFixtureClass;

it('discovers only concrete implementations of the configured contract', function (): void {
    $root = dirname(__DIR__, 2).'/Fixtures/ImportPlugin';

    require_once $root.'/src/ConcreteFixtureImporter.php';
    require_once $root.'/src/AbstractFixtureImporter.php';
    require_once $root.'/src/UnrelatedFixtureClass.php';

    $discovery = new FilesystemImportPluginDiscovery(
        label: 'fixture-importers',
        defaultRoot: $root,
        contract: BulkImporterInterface::class,
    );

    expect($discovery->label())->toBe('fixture-importers')
        ->and($discovery->root())->toBe($root)
        ->and($discovery->autoloadPath())->toBeNull()
        ->and($discovery->discoverImplementations())->toBe([ConcreteFixtureImporter::class])
        ->and($discovery->discoverImplementations())->not->toContain(
            AbstractFixtureImporter::class,
            UnrelatedFixtureClass::class,
        );
});

it('returns no plugin details when the configured root does not exist', function (): void {
    $discovery = new FilesystemImportPluginDiscovery(
        label: 'missing-importers',
        defaultRoot: sys_get_temp_dir().'/missing-importers-'.uniqid('', true),
    );

    expect($discovery->root())->toBeNull()
        ->and($discovery->autoloadPath())->toBeNull()
        ->and($discovery->discoverImplementations())->toBe([]);
});

it('rejects contracts outside the Core importer boundary', function (): void {
    expect(fn (): FilesystemImportPluginDiscovery => new FilesystemImportPluginDiscovery(
        label: 'invalid-importers',
        defaultRoot: sys_get_temp_dir(),
        contract: stdClass::class,
    ))->toThrow(InvalidArgumentException::class, 'must extend');
});
