<?php

declare(strict_types=1);

use Modules\Core\Filament\FilamentTraitResolver;
use Modules\Core\Filament\Utils\HasForm as CoreHasForm;
use Modules\Core\Filament\Utils\HasRecords as CoreHasRecords;
use Modules\Core\Filament\Utils\HasTable as CoreHasTable;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Stubs\DynamicEntities\DynamicContentStubModel;

it('resolves Core traits for App namespaces', function (): void {
    expect(FilamentTraitResolver::resolve('App\\Filament\\Resources\\Users\\Tables\\UsersTable', 'HasTable'))
        ->toBe(CoreHasTable::class)
        ->and(FilamentTraitResolver::resolve('App\\Filament\\Resources\\Users\\Schemas\\UserForm', 'HasForm'))
        ->toBe(CoreHasForm::class)
        ->and(FilamentTraitResolver::resolve('App\\Filament\\Resources\\Users\\Pages\\ListUsers', 'HasRecords'))
        ->toBe(CoreHasRecords::class);
});

it('falls back to Core traits for a module that defines none of its own', function (): void {
    expect(FilamentTraitResolver::resolve('Modules\\WithoutTraits\\Filament\\Resources\\Things\\Tables\\ThingsTable', 'HasTable'))
        ->toBe(CoreHasTable::class)
        ->and(FilamentTraitResolver::resolve('Modules\\WithoutTraits\\Filament\\Resources\\Things\\Pages\\ListThings', 'HasRecords'))
        ->toBe(CoreHasRecords::class)
        ->and(FilamentTraitResolver::resolve('Modules\\WithoutTraits\\Filament\\Resources\\Things\\Schemas\\ThingForm', 'HasForm'))
        ->toBe(CoreHasForm::class);
});

it('lists HasForm-owned columns only for HasDynamicContents models', function (): void {
    expect(FilamentTraitResolver::formColumnsOwnedByHasForm(DynamicContentStubModel::class))
        ->toBe(['entity_id', 'presettable_id'])
        ->and(FilamentTraitResolver::formColumnsOwnedByHasForm(Setting::class))
        ->toBe([]);
});

it('lists HasTable strip columns including timestamp and validity grezzi', function (): void {
    expect(FilamentTraitResolver::tableColumnsOwnedByHasTable(Setting::class))
        ->toContain('created_at', 'updated_at', 'deleted_at', 'valid_from', 'valid_to');
});

it('lists computed attributes that must never reach filament form state', function (): void {
    expect(FilamentTraitResolver::computedAttributesNeverInForms(DynamicContentStubModel::class))
        ->toContain('statistics', 'is_locked', 'is_deleted');
});
