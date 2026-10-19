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

it('refuses to run until compaction is redefined, and writes no version', function (array $arguments): void {
    $role = Modules\Core\Models\Role::factory()->create();
    $versions = Modules\Core\Models\Version::query()->count();

    $command = app(CompactVersions::class);
    $command->setLaravel(app());
    $tester = new CommandTester($command);
    $tester->execute($arguments === [] ? [] : [...$arguments, 'id' => (string) $role->getKey()]);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('not available')
        ->and(Modules\Core\Models\Version::query()->count())->toBe($versions);
})->with([
    'every record' => [[]],
    'one record' => [['modelClass' => Modules\Core\Models\Role::class]],
]);
