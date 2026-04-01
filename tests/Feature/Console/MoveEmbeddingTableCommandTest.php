<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Console\MoveEmbeddingTable;
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

    expect(Schema::hasTable('model_embeddings'))->toBeTrue();
    expect($command->handle())->toBe(0);
});

it('recreates model embeddings table when missing on target connection', function (): void {
    Schema::dropIfExists('model_embeddings');

    $command = moveEmbeddingCommandWithOutput(app(MoveEmbeddingTable::class));
    expect($command->handle())->toBe(0);
    expect(Schema::hasTable('model_embeddings'))->toBeTrue();
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
