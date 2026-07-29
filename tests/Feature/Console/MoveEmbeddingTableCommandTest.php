<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Console\MoveEmbeddingTable;
use Modules\Core\Enums\CoreTables;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function moveEmbeddingCommandWithOutput(MoveEmbeddingTable $command): MoveEmbeddingTable
{
    $command->setLaravel(app());
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $reflection = new ReflectionProperty(Illuminate\Console\Command::class, 'output');
    $reflection->setAccessible(true);
    $reflection->setValue($command, $output);

    return $command;
}

it('reports when model embeddings already exist on target connection', function (): void {
    $command = moveEmbeddingCommandWithOutput(app(MoveEmbeddingTable::class));

    expect(Schema::hasTable(CoreTables::ModelEmbeddings->value))->toBeTrue();
    expect($command->handle())->toBe(0);
});

it('recreates model embeddings table when missing on target connection', function (): void {
    Schema::dropIfExists(CoreTables::ModelEmbeddings->value);

    $command = moveEmbeddingCommandWithOutput(app(MoveEmbeddingTable::class));
    expect($command->handle())->toBe(0);
    expect(Schema::hasTable(CoreTables::ModelEmbeddings->value))->toBeTrue();
});

it('recreates model embeddings table on the model connection instead of the default connection', function (): void {
    $previous_default = config('database.default');
    $previous_resolver = Model::getConnectionResolver();
    $legacy_connection = 'embedding_legacy';
    $target_connection = 'embedding_target';

    foreach ([$legacy_connection, $target_connection] as $connection_name) {
        config()->set("database.connections.{$connection_name}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($connection_name);
    }

    config()->set('database.default', $legacy_connection);

    $resolver = Mockery::mock(ConnectionResolverInterface::class);
    $resolver->shouldReceive('connection')
        ->with(null)
        ->andReturnUsing(static fn () => DB::connection($target_connection));
    Model::setConnectionResolver($resolver);

    try {
        $command = moveEmbeddingCommandWithOutput(app(MoveEmbeddingTable::class));

        expect($command->handle())->toBe(0)
            ->and(Schema::connection($target_connection)->hasTable(CoreTables::ModelEmbeddings->value))->toBeTrue()
            ->and(Schema::connection($legacy_connection)->hasTable(CoreTables::ModelEmbeddings->value))->toBeFalse();
    } finally {
        Model::setConnectionResolver($previous_resolver);
        config()->set('database.default', $previous_default);
        DB::purge($legacy_connection);
        DB::purge($target_connection);
    }
});

it('resolves embeddings migration file path to an existing migration on disk', function (): void {
    $command = app(MoveEmbeddingTable::class);
    $resolver = new ReflectionMethod(MoveEmbeddingTable::class, 'modelEmbeddingsMigrationFile');
    $resolver->setAccessible(true);
    $path = $resolver->invoke($command);

    expect($path)->toBeString()
        ->and(is_file($path))->toBeTrue()
        ->and($path)->toContain('create_model_embeddings_table');
});
