<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Lang;
use Modules\Core\Filament\Utils\AttributeLabelResolver;
use Modules\Core\Models\User;

/**
 * Filament labels an attribute from the owning module's `fields` keys, then from the shared `app.fields`,
 * and only then from the attribute name. The model of a field or entry here is a class name in a module
 * namespace that does not exist: the resolver reads the namespace, never the class, so the test needs no
 * module but Core.
 */
beforeEach(function (): void {
    Lang::addLines([
        'demo.fields.planned_start_at' => 'Planned start',
        'demo.fields.work_center' => 'Work center',
        'demo.fields.work_center_code' => 'Work center code',
    ], 'en');
    Lang::addLines([
        'demo.fields.planned_start_at' => 'Inizio pianificato',
        'demo.fields.work_center' => 'Postazione',
        'demo.fields.work_center_code' => 'Codice postazione',
    ], 'it');
});

function labelResolverModel(): string
{
    return 'Modules\\Demo\\Models\\Widget';
}

function labelResolverField(string $name): TextInput
{
    $field = TextInput::make($name);
    $field->container(Schema::make()->model(labelResolverModel()));

    return $field;
}

function labelResolverUserTable(): Table
{
    return Table::make(Mockery::mock(HasTable::class))->query(static fn () => User::query());
}

it('labels a field from its module fields key', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    expect(labelResolverField('planned_start_at')->getLabel())->toBe($expected);
})->with([
    'en' => ['en', 'Planned start'],
    'it' => ['it', 'Inizio pianificato'],
]);

it('falls back to the shared app fields', function (): void {
    app()->setLocale('it');

    expect(labelResolverField('created_at')->getLabel())->toBe('Creato il');
});

it('tries the underscored relation path before the segment Filament labels by', function (): void {
    app()->setLocale('it');

    expect(labelResolverField('work_center.code')->getLabel())->toBe('Codice postazione')
        ->and(labelResolverField('work_center.name')->getLabel())->toBe('Nome');
});

it('keeps the label Filament derives when no key exists', function (): void {
    app()->setLocale('it');

    expect(labelResolverField('zz_unknown_attribute')->getLabel())->toBe('Zz unknown attribute');
});

it('never overrides a label written in the code', function (): void {
    app()->setLocale('it');

    $field = TextInput::make('planned_start_at')->label('Start');
    $field->container(Schema::make()->model(labelResolverModel()));

    expect($field->getLabel())->toBe('Start');
});

it('labels an infolist entry like a field', function (): void {
    app()->setLocale('it');

    $entry = TextEntry::make('planned_start_at');
    $entry->container(Schema::make()->model(labelResolverModel()));

    expect($entry->getLabel())->toBe('Inizio pianificato');
});

it('labels table columns and filters of a Core model from app fields', function (): void {
    app()->setLocale('it');
    $table = labelResolverUserTable();

    expect(TextColumn::make('created_at')->table($table)->getLabel())->toBe('Creato il')
        ->and(TextColumn::make('user.name')->table($table)->getLabel())->toBe('Utente')
        ->and(SelectFilter::make('status')->table($table)->getLabel())->toBe('Stato');
});

it('uses app for components without a model', function (): void {
    app()->setLocale('it');

    $field = TextInput::make('name');
    $field->container(Schema::make());

    expect($field->getLabel())->toBe('Nome');
});

it('maps a model class to its module lang group', function (?string $model, string $group): void {
    expect(app(AttributeLabelResolver::class)->groupFor($model))->toBe($group);
})->with([
    'module model' => ['Modules\\MES\\Models\\ProductionOrder', 'mes'],
    'core model' => [User::class, 'app'],
    'application model' => ['App\\Models\\User', 'app'],
    'no model' => [null, 'app'],
]);
