<?php

declare(strict_types=1);

use Modules\Core\Locking\Console\ModelLockingRefreshCommand;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;

it('lock refresh command merges application quiet option without duplicate definition', function (): void {
    $command = app(ModelLockingRefreshCommand::class);
    $command->setLaravel(app());
    $command->setApplication(new SymfonyConsoleApplication('coverage', '1.0.0'));
    $command->mergeApplicationDefinition();

    expect($command->getDefinition()->hasOption('quiet'))->toBeTrue();
});
