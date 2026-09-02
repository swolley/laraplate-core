<?php

declare(strict_types=1);

use Modules\Core\Authorization\CorePermissions;
use Modules\Core\Authorization\PermissionManifest;
use Modules\Core\Models\Approval;
use Modules\Core\Support\PermissionName;
use Modules\ERP\Authorization\ERPPermissions;
use Modules\ERP\Models\Invoice;

it('collects the domain permissions declared by the enabled modules', function (): void {
    $names = app(PermissionManifest::class)->names();

    expect($names)->toContain(PermissionName::forClass(Invoice::class, 'post'))
        ->and($names)->toContain(PermissionName::forClass(Invoice::class, 'force_post'));
});

it('returns one module slice so a seeder can materialize only its own names', function (): void {
    $manifest = app(PermissionManifest::class);

    $expected = [];

    foreach (ERPPermissions::operations() as $model_class => $operations) {
        foreach ($operations as $operation) {
            $expected[] = PermissionName::forClass($model_class, $operation);
        }
    }

    expect($manifest->namesFor('ERP'))->toBe($expected)
        ->and($manifest->namesFor('Core'))->toBe([])
        ->and($manifest->namesFor('NotAModule'))->toBe([]);
});

it('never repeats a name, whichever module declared it', function (): void {
    $names = app(PermissionManifest::class)->names();

    expect($names)->toBe(array_values(array_unique($names)));
});

it('collects the models the modules keep out of CRUD generation', function (): void {
    expect(app(PermissionManifest::class)->excludedModels())
        ->toContain(Approval::class)
        ->toContain(...CorePermissions::excludedModels());
});

it('declares no operation for a model it also excludes', function (): void {
    $manifest = app(PermissionManifest::class);
    $excluded = $manifest->excludedModels();

    foreach (array_keys($manifest->operations()) as $model_class) {
        expect($excluded)->not->toContain($model_class);
    }
});
