<?php

declare(strict_types=1);

use Filament\Commands\FileGenerators\Resources\Pages\ResourceListRecordsPageClassGenerator;
use Filament\Commands\FileGenerators\Resources\ResourceClassGenerator;
use Filament\Commands\FileGenerators\Resources\Schemas\ResourceFormSchemaClassGenerator;
use Filament\Commands\FileGenerators\Resources\Schemas\ResourceTableClassGenerator;
use Modules\Core\Filament\Generators\LaraplateResourceClassGenerator;
use Modules\Core\Filament\Generators\LaraplateResourceFormSchemaClassGenerator;
use Modules\Core\Filament\Generators\LaraplateResourceListRecordsPageClassGenerator;
use Modules\Core\Filament\Generators\LaraplateResourceTableClassGenerator;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Setting;

it('binds laraplate filament generators in the container', function (): void {
    expect(app(ResourceClassGenerator::class, [
        'fqn' => 'App\\Filament\\Resources\\Settings\\SettingResource',
        'modelFqn' => Setting::class,
        'pageRoutes' => [],
        'formSchemaFqn' => null,
        'infolistSchemaFqn' => null,
        'tableFqn' => null,
        'clusterFqn' => null,
        'parentResourceFqn' => null,
        'recordTitleAttribute' => null,
        'hasViewOperation' => false,
        'isGenerated' => false,
        'isSoftDeletable' => false,
        'isSimple' => false,
    ]))->toBeInstanceOf(LaraplateResourceClassGenerator::class);

    expect(app(ResourceTableClassGenerator::class, [
        'fqn' => 'App\\Filament\\Resources\\Settings\\Tables\\SettingsTable',
        'modelFqn' => Setting::class,
        'parentResourceFqn' => null,
        'hasViewOperation' => false,
        'isGenerated' => false,
        'isSoftDeletable' => false,
        'isSimple' => false,
    ]))->toBeInstanceOf(LaraplateResourceTableClassGenerator::class);

    expect(app(ResourceFormSchemaClassGenerator::class, [
        'fqn' => 'App\\Filament\\Resources\\Settings\\Schemas\\SettingForm',
        'modelFqn' => Setting::class,
        'parentResourceFqn' => null,
        'isGenerated' => false,
    ]))->toBeInstanceOf(LaraplateResourceFormSchemaClassGenerator::class);

    expect(app(ResourceListRecordsPageClassGenerator::class, [
        'fqn' => 'App\\Filament\\Resources\\Settings\\Pages\\ListSettings',
        'resourceFqn' => 'App\\Filament\\Resources\\Settings\\SettingResource',
    ]))->toBeInstanceOf(LaraplateResourceListRecordsPageClassGenerator::class);
});

it('emits HasTable and configureTable in generated table source', function (): void {
    $generator = app(ResourceTableClassGenerator::class, [
        'fqn' => 'App\\Filament\\Resources\\Settings\\Tables\\SettingsTable',
        'modelFqn' => Setting::class,
        'parentResourceFqn' => null,
        'hasViewOperation' => false,
        'isGenerated' => false,
        'isSoftDeletable' => false,
        'isSimple' => false,
    ]);

    $source = $generator->generate();

    expect($source)->toContain(HasTable::class)
        ->and($source)->toContain('configureTable');
});

it('passes generated domain columns into HasTable callback and omits timestamp grezzi', function (): void {
    $generator = app(ResourceTableClassGenerator::class, [
        'fqn' => 'App\\Filament\\Resources\\Settings\\Tables\\SettingsTable',
        'modelFqn' => Setting::class,
        'parentResourceFqn' => null,
        'hasViewOperation' => false,
        'isGenerated' => true,
        'isSoftDeletable' => false,
        'isSimple' => false,
    ]);

    $source = $generator->generate();

    expect($source)->toContain('columns: static function')
        ->and($source)->toContain('->unshift(')
        ->and($source)->not->toContain("make('created_at')")
        ->and($source)->not->toContain("make('updated_at')")
        ->and($source)->not->toContain("make('deleted_at')")
        ->and($source)->not->toContain("make('valid_from')")
        ->and($source)->not->toContain("make('valid_to')");
});

it('emits HasForm and configureForm in generated form source', function (): void {
    $generator = app(ResourceFormSchemaClassGenerator::class, [
        'fqn' => 'App\\Filament\\Resources\\Settings\\Schemas\\SettingForm',
        'modelFqn' => Setting::class,
        'parentResourceFqn' => null,
        'isGenerated' => false,
    ]);

    $source = $generator->generate();

    expect($source)->toContain(HasForm::class)
        ->and($source)->toContain('configureForm')
        ->and($source)->toContain('return self::configureForm(');
});

it('excludes HasForm-owned columns from generated HasDynamicContents forms', function (): void {
    $generator = app(ResourceFormSchemaClassGenerator::class, [
        'fqn' => 'Modules\\CMS\\Filament\\Resources\\Contents\\Schemas\\ContentForm',
        'modelFqn' => \Modules\CMS\Models\Content::class,
        'parentResourceFqn' => null,
        'isGenerated' => true,
    ]);

    $source = $generator->generate();

    expect($source)->toContain(HasForm::class)
        ->and($source)->not->toContain("make('entity_id')")
        ->and($source)->not->toContain("make('presettable_id')");
});

it('emits HasRecords on generated list pages', function (): void {
    $generator = app(ResourceListRecordsPageClassGenerator::class, [
        'fqn' => 'App\\Filament\\Resources\\Settings\\Pages\\ListSettings',
        'resourceFqn' => 'App\\Filament\\Resources\\Settings\\SettingResource',
    ]);

    $source = $generator->generate();

    expect($source)->toContain(HasRecords::class);
});
