<?php

declare(strict_types=1);

use Modules\Core\Filament\FilamentTraitResolver;
use Modules\CMS\Filament\Utils\HasRecords as CmsHasRecords;
use Modules\CMS\Filament\Utils\HasTable as CmsHasTable;
use Modules\Core\Filament\Utils\HasForm as CoreHasForm;
use Modules\Core\Filament\Utils\HasRecords as CoreHasRecords;
use Modules\Core\Filament\Utils\HasTable as CoreHasTable;

it('resolves Core traits for App namespaces', function (): void {
    expect(FilamentTraitResolver::resolve('App\\Filament\\Resources\\Users\\Tables\\UsersTable', 'HasTable'))
        ->toBe(CoreHasTable::class)
        ->and(FilamentTraitResolver::resolve('App\\Filament\\Resources\\Users\\Schemas\\UserForm', 'HasForm'))
        ->toBe(CoreHasForm::class)
        ->and(FilamentTraitResolver::resolve('App\\Filament\\Resources\\Users\\Pages\\ListUsers', 'HasRecords'))
        ->toBe(CoreHasRecords::class);
});

it('resolves CMS traits when present', function (): void {
    expect(FilamentTraitResolver::resolve('Modules\\CMS\\Filament\\Resources\\Tags\\Tables\\TagsTable', 'HasTable'))
        ->toBe(CmsHasTable::class)
        ->and(FilamentTraitResolver::resolve('Modules\\CMS\\Filament\\Resources\\Tags\\Pages\\ListTags', 'HasRecords'))
        ->toBe(CmsHasRecords::class)
        ->and(FilamentTraitResolver::resolve('Modules\\CMS\\Filament\\Resources\\Tags\\Schemas\\TagForm', 'HasForm'))
        ->toBe(CoreHasForm::class);
});

it('falls back to Core traits for ERP', function (): void {
    expect(FilamentTraitResolver::resolve('Modules\\ERP\\Filament\\Resources\\Companies\\Tables\\CompaniesTable', 'HasTable'))
        ->toBe(CoreHasTable::class)
        ->and(FilamentTraitResolver::resolve('Modules\\ERP\\Filament\\Resources\\Companies\\Pages\\ListCompanies', 'HasRecords'))
        ->toBe(CoreHasRecords::class);
});
