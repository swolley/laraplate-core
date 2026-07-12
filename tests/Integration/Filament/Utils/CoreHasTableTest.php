<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Contracts\HasTable as HasTableContract;
use Filament\Tables\Table;
use Modules\Core\Tests\Unit\Filament\Utils\CoreHasTableTraitHarness;

it('centers translations flag column alignment', function (): void {
    $livewire = Mockery::mock(HasTableContract::class)->shouldIgnoreMissing();
    $table = Table::make($livewire)->query(User::query());
    $model_instance = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();

    $configure_columns = new ReflectionMethod(CoreHasTableTraitHarness::class, 'configureColumns');
    $configure_columns->setAccessible(true);
    $configure_columns->invoke(
        null,
        $table,
        false,
        false,
        false,
        false,
        false,
        true,
        null,
        $model_instance,
    );

    $translations_column = collect($table->getColumns())
        ->first(static fn ($column): bool => $column->getName() === 'translations.locale');

    expect($translations_column)->not->toBeNull();
    expect($translations_column->getAlignment())->toBe(Alignment::Center);
});
