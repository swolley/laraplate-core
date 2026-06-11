<?php

declare(strict_types=1);

use Modules\Core\SoftDeletes\Console\ModelSoftDeletesAddCommand;
use Modules\Core\SoftDeletes\Console\ModelSoftDeletesRefreshCommand;
use Modules\Core\SoftDeletes\Console\ModelSoftDeletesRemoveCommand;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;

it('defines expected command signatures', function (): void {
    $add = new ReflectionClass(ModelSoftDeletesAddCommand::class);
    $remove = new ReflectionClass(ModelSoftDeletesRemoveCommand::class);
    $refresh = new ReflectionClass(ModelSoftDeletesRefreshCommand::class);

    expect(file_get_contents($add->getFileName()))->toContain('model:soft-deletes-add')
        ->and(file_get_contents($remove->getFileName()))->toContain('model:soft-deletes-remove')
        ->and(file_get_contents($refresh->getFileName()))->toContain('model:soft-deletes-refresh');
});

it('remove command includes mandatory purge safeguard for logical deletions', function (): void {
    $reflection = new ReflectionClass(ModelSoftDeletesRemoveCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('logical-deleted records')
        ->and($source)->toContain('Purge those records with hard delete before removing soft delete columns?')
        ->and($source)->toContain('Operation cancelled. No columns were removed.');
});

it('refresh command reconciles trait-schema-setting consistency', function (): void {
    $reflection = new ReflectionClass(ModelSoftDeletesRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('model:soft-deletes-add')
        ->and($source)->toContain('model:soft-deletes-remove')
        ->and($source)->toContain('soft_deletes_');
});

it('refresh command merges application quiet option without duplicate definition', function (): void {
    $command = app(ModelSoftDeletesRefreshCommand::class);
    $command->setLaravel(app());
    $command->setApplication(new SymfonyConsoleApplication('coverage', '1.0.0'));
    $command->mergeApplicationDefinition();

    expect($command->getDefinition()->hasOption('quiet'))->toBeTrue();
});

