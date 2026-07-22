<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Import\Support\BulkImportRunner;
use Modules\Core\Import\Support\ContainerBulkImporterResolver;
use Modules\Core\Tests\Stubs\Import\FakeBulkImporter;
use Modules\Core\Tests\Stubs\Import\FakeBulkImporterResolver;
use Modules\Core\Tests\Stubs\Import\FakeImportPluginDiscovery;
use Modules\Core\Tests\Stubs\Import\TestImportCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    Schema::create(FakeBulkImporter::TABLE, static function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    FakeBulkImporter::$arguments = [];
});

afterEach(function (): void {
    Schema::dropIfExists(FakeBulkImporter::TABLE);
});

it('defines the shared module import command contract without a signature', function (): void {
    $command = new TestImportCommand(
        app(BulkImportRunner::class),
        new FakeBulkImporterResolver(app()),
        new FakeImportPluginDiscovery,
    );
    $definition = $command->getDefinition();

    expect($command->getName())->toBe('test:import')
        ->and($definition->getArguments())->toBe([])
        ->and(array_keys($definition->getOptions()))->toContain(
            'importer',
            'bootstrap',
            'arg',
            'dry-run',
            'limit',
            'no-search',
        )
        ->and($definition->getOption('arg')->isArray())->toBeTrue()
        ->and($definition->getOption('dry-run')->acceptValue())->toBeFalse()
        ->and($definition->getOption('limit')->isValueOptional())->toBeTrue();
});

it('resolves importer constructor parameters and reports imported records', function (): void {
    $command = new TestImportCommand(
        app(BulkImportRunner::class),
        new FakeBulkImporterResolver(app()),
        new FakeImportPluginDiscovery,
    );
    [$status, $output] = runCoreImportCommand($command, [
        '--importer' => FakeBulkImporter::class,
        '--arg' => ['records=4', 'ignored', '=blank', 'records=3'],
        '--limit' => 2,
    ]);

    expect($status)->toBe(TestImportCommand::SUCCESS)
        ->and($output)->toContain('Imported 2 record(s).')
        ->and(DB::table(FakeBulkImporter::TABLE)->count())->toBe(2)
        ->and(FakeBulkImporter::$arguments)->toBe([
            'records' => '3',
            'dryRun' => false,
            'limit' => 2,
        ]);
});

it('rolls back default connection writes in dry run', function (): void {
    $command = new TestImportCommand(
        app(BulkImportRunner::class),
        new FakeBulkImporterResolver(app()),
        new FakeImportPluginDiscovery,
    );
    [$status, $output] = runCoreImportCommand($command, [
        '--importer' => FakeBulkImporter::class,
        '--arg' => ['records=2'],
        '--dry-run' => true,
    ]);

    expect($status)->toBe(TestImportCommand::SUCCESS)
        ->and($output)->toContain('default database transaction will be rolled back')
        ->and($output)->toContain('Search indexing disabled')
        ->and(DB::table(FakeBulkImporter::TABLE)->count())->toBe(0)
        ->and(FakeBulkImporter::$arguments['dryRun'])->toBeTrue();
});

it('validates importer classes through the injected resolver contract', function (): void {
    $resolver = new ContainerBulkImporterResolver(app());
    $command = new TestImportCommand(
        app(BulkImportRunner::class),
        $resolver,
        new FakeImportPluginDiscovery,
    );
    [$status, $output] = runCoreImportCommand($command, ['--importer' => stdClass::class]);

    expect($status)->toBe(TestImportCommand::FAILURE)
        ->and($output)->toContain('must implement');
});

it('does not register a runnable Core import command', function (): void {
    expect(Artisan::all())->not->toHaveKey('core:import');
});

/**
 * @param  array<string, mixed>  $input
 * @return array{int, string}
 */
function runCoreImportCommand(TestImportCommand $command, array $input): array
{
    $command->setLaravel(app());
    $output = new BufferedOutput;
    $status = $command->run(new ArrayInput($input), $output);

    return [$status, $output->fetch()];
}
