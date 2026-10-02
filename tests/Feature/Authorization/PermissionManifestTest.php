<?php

declare(strict_types=1);

use Modules\Core\Authorization\Contracts\DeclaresPermissions;
use Modules\Core\Authorization\CorePermissions;
use Modules\Core\Authorization\PermissionManifest;
use Modules\Core\Models\Approval;
use Modules\Core\Support\PermissionName;

/**
 * The permission declarations of the enabled modules, found by the same convention the
 * manifest uses, so these tests hold for whichever modules are installed.
 *
 * @return array<string, class-string<DeclaresPermissions>>
 */
function installedPermissionDeclarations(): array
{
    $declarations = [];

    foreach (modules(prioritySort: false) as $module) {
        $class = sprintf('Modules\\%s\\Authorization\\%sPermissions', $module, $module);

        if (class_exists($class)) {
            $declarations[$module] = $class;
        }
    }

    return $declarations;
}

/**
 * @param  class-string<DeclaresPermissions>  $declaration
 * @return list<string>
 */
function declaredPermissionNames(string $declaration): array
{
    $names = [];

    foreach ($declaration::operations() as $model_class => $operations) {
        foreach ($operations as $operation) {
            $names[] = PermissionName::forClass($model_class, $operation);
        }
    }

    return array_values(array_unique($names));
}

it('collects the domain permissions declared by the enabled modules', function (): void {
    $names = app(PermissionManifest::class)->names();

    foreach (installedPermissionDeclarations() as $declaration) {
        foreach (declaredPermissionNames($declaration) as $declared_name) {
            expect($names)->toContain($declared_name);
        }
    }
});

it('returns one module slice so a seeder can materialize only its own names', function (): void {
    $manifest = app(PermissionManifest::class);

    foreach (installedPermissionDeclarations() as $module => $declaration) {
        expect($manifest->namesFor($module))->toBe(declaredPermissionNames($declaration));
    }

    expect($manifest->namesFor('NotAModule'))->toBe([]);
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
