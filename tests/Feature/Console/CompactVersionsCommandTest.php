<?php

declare(strict_types=1);

use Modules\Core\Console\CompactVersions;
use Symfony\Component\Console\Tester\CommandTester;

it('fails when id is given without model class', function (): void {
    $command = app(CompactVersions::class);
    $command->setLaravel(app());
    $tester = new CommandTester($command);
    $tester->execute(['id' => '1']);

    expect($tester->getStatusCode())->toBe(1);
});

it('fails when model class does not exist', function (): void {
    $command = app(CompactVersions::class);
    $command->setLaravel(app());
    $tester = new CommandTester($command);
    $tester->execute([
        'modelClass' => 'App\\Models\\ThisModelClassDoesNotExistForCompactVersionsTest',
        'id' => '1',
    ]);

    expect($tester->getStatusCode())->toBe(1);
});
