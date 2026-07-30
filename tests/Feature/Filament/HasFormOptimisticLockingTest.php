<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Locking\Traits\HasOptimisticLocking;
use Modules\Core\Tests\Stubs\Filament\HasFormHarness;
use Modules\Core\Tests\Stubs\Locking\OptimisticLockModel;
use Modules\Core\Tests\Stubs\Locking\PlainLockModel;

/**
 * @return array<string, \Filament\Schemas\Components\Component>
 */
function componentsByName(Schema $schema): array
{
    $by_name = [];

    foreach ($schema->getComponents(withHidden: true) as $component) {
        if (method_exists($component, 'getName')) {
            $by_name[$component->getName()] = $component;
        }
    }

    return $by_name;
}

it('injects the lock version field for models using HasOptimisticLocking', function (): void {
    $schema = HasFormHarness::run(
        Schema::make()
            ->model(OptimisticLockModel::class)
            ->components([TextInput::make('name')]),
    );

    $column = OptimisticLockModel::lockVersionColumn();

    expect(componentsByName($schema))->toHaveKey($column);
});

it('does not inject the lock version field for models without the trait', function (): void {
    expect(class_uses_trait(PlainLockModel::class, HasOptimisticLocking::class))->toBeFalse();

    $schema = HasFormHarness::run(
        Schema::make()
            ->model(PlainLockModel::class)
            ->components([TextInput::make('name')]),
    );

    $names = array_keys(componentsByName($schema));

    expect($names)->toBe(['name']);
});

it('keeps the lock version field hidden and dehydrated so it survives the round trip', function (): void {
    $schema = HasFormHarness::run(
        Schema::make()
            ->model(OptimisticLockModel::class)
            ->components([TextInput::make('name')]),
    );

    $field = componentsByName($schema)[OptimisticLockModel::lockVersionColumn()] ?? null;

    expect($field)->not->toBeNull()
        // Must reach the client and come back: a component excluded from
        // rendering would leave the guard value out of the submitted data.
        ->and($field->isDehydrated())->toBeTrue();
});

it('never drops the components the resource declared', function (): void {
    foreach ([OptimisticLockModel::class, PlainLockModel::class] as $model) {
        $schema = HasFormHarness::run(
            Schema::make()
                ->model($model)
                ->components([TextInput::make('name'), TextInput::make('note')]),
        );

        expect(array_keys(componentsByName($schema)))
            ->toContain('name', 'note');
    }
});

it('does not force the user to supply the lock version', function (): void {
    $schema = HasFormHarness::run(
        Schema::make()
            ->model(OptimisticLockModel::class)
            ->components([TextInput::make('name')]),
    );

    $field = componentsByName($schema)[OptimisticLockModel::lockVersionColumn()] ?? null;

    // A required guard field would block creation, where no version exists yet.
    expect($field)->not->toBeNull()
        ->and($field->isRequired())->toBeFalse();
});
