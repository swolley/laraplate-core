<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Events\ModelsRequireIndexing;
use Modules\Core\Import\Support\BulkImportRunner;
use Modules\Core\Import\Support\ContainerBulkImporterResolver;
use Modules\Core\Tests\Stubs\Import\FakeBulkImporter;
use Modules\Core\Tests\Stubs\Import\FakeBulkImporterResolver;
use Modules\Core\Tests\Stubs\Import\FakeConnectionAwareBulkImporter;
use Modules\Core\Tests\Stubs\Import\FakeImportPluginDiscovery;
use Modules\Core\Tests\Stubs\Import\FakeSearchableBulkImporter;
use Modules\Core\Tests\Stubs\Import\TestImportCommand;
use Modules\Core\Tests\Stubs\Search\DeferredSearchableStubModel;
use Modules\Core\Tests\Stubs\Search\RecordingSearchEngineStub;
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
            'index-batch',
        )
        ->and($definition->getOption('arg')->isArray())->toBeTrue()
        ->and($definition->getOption('dry-run')->acceptValue())->toBeFalse()
        ->and($definition->getOption('dry-run')->getDescription())->toContain('importer-selected or default')
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
        ->and($output)->toContain('selected database transaction will be rolled back')
        ->and($output)->toContain('Search indexing disabled')
        ->and(DB::table(FakeBulkImporter::TABLE)->count())->toBe(0)
        ->and(FakeBulkImporter::$arguments['dryRun'])->toBeTrue();
});

it('rolls back writes on the connection declared by the importer', function (): void {
    config([
        'database.connections.import_affinity' => [
            ...config('database.connections.sqlite'),
            'database' => ':memory:',
        ],
    ]);
    DB::purge('import_affinity');
    Schema::connection('import_affinity')->create(FakeBulkImporter::TABLE, static function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    try {
        $command = new TestImportCommand(
            app(BulkImportRunner::class),
            new FakeBulkImporterResolver(app()),
            new FakeImportPluginDiscovery,
        );
        [$status] = runCoreImportCommand($command, [
            '--importer' => FakeConnectionAwareBulkImporter::class,
            '--arg' => [
                'connectionName=import_affinity',
                'table=' . FakeBulkImporter::TABLE,
            ],
            '--dry-run' => true,
        ]);

        expect($status)->toBe(TestImportCommand::SUCCESS)
            ->and(DB::connection('import_affinity')->table(FakeBulkImporter::TABLE)->count())->toBe(0);
    } finally {
        Schema::connection('import_affinity')->dropIfExists(FakeBulkImporter::TABLE);
        DB::purge('import_affinity');
    }
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

it('indexes imported records in bulk flushes of --index-batch', function (): void {
    DeferredSearchableStubModel::createTable();
    DeferredSearchableStubModel::$engine = new RecordingSearchEngineStub;
    config(['scout.queue' => true]);
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $command = new TestImportCommand(
        app(BulkImportRunner::class),
        new FakeBulkImporterResolver(app()),
        new FakeImportPluginDiscovery,
    );

    try {
        [$status] = runCoreImportCommand($command, [
            '--importer' => FakeSearchableBulkImporter::class,
            '--arg' => ['records=5'],
            '--index-batch' => 2,
        ]);

        expect($status)->toBe(TestImportCommand::SUCCESS);
        expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 2, 1]);
        Event::assertNotDispatched(ModelRequiresIndexing::class);
    } finally {
        DeferredSearchableStubModel::dropTable();
        DeferredSearchableStubModel::$engine = null;
    }
});

it('imports without indexing or embedding anything with --no-search', function (): void {
    DeferredSearchableStubModel::createTable();
    DeferredSearchableStubModel::$engine = new RecordingSearchEngineStub;
    config(['scout.queue' => true]);
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $command = new TestImportCommand(
        app(BulkImportRunner::class),
        new FakeBulkImporterResolver(app()),
        new FakeImportPluginDiscovery,
    );

    try {
        [$status] = runCoreImportCommand($command, [
            '--importer' => FakeSearchableBulkImporter::class,
            '--arg' => ['records=3'],
            '--no-search' => true,
        ]);

        expect($status)->toBe(TestImportCommand::SUCCESS);
        expect(DeferredSearchableStubModel::query()->count())->toBe(3);
        expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
        Event::assertNotDispatched(ModelRequiresIndexing::class);
        Event::assertNotDispatched(ModelsRequireIndexing::class);
    } finally {
        DeferredSearchableStubModel::dropTable();
        DeferredSearchableStubModel::$engine = null;
    }
});

it('stops an interrupted import with status 130 after indexing what it imported', function (): void {
    DeferredSearchableStubModel::createTable();
    DeferredSearchableStubModel::$engine = new RecordingSearchEngineStub;
    config(['scout.queue' => true]);
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $command = new TestImportCommand(
        app(BulkImportRunner::class),
        new FakeBulkImporterResolver(app()),
        new FakeImportPluginDiscovery,
    );

    try {
        [$status, $output] = runCoreImportCommand($command, [
            '--importer' => FakeSearchableBulkImporter::class,
            '--arg' => ['records=5', 'interruptAfter=2'],
        ]);

        expect($status)->toBe(130);
        expect($output)->toContain('Import interrupted');
        expect(DeferredSearchableStubModel::query()->count())->toBe(2);
        expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2]);
    } finally {
        DeferredSearchableStubModel::dropTable();
        DeferredSearchableStubModel::$engine = null;
    }
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
