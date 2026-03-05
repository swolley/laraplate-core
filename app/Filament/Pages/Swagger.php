<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

final class Swagger extends Page
{
    #[Override]
    protected static ?string $navigationLabel = 'API Documentation';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Documentation';

    #[Override]
    protected static ?string $slug = 'documentation/swagger';

    #[Override]
    protected static ?int $navigationSort = 2;

    #[Override]
    protected string $view = 'core::filament.pages.swagger';

    public function mount(): void
    {
        //
    }

    public function getSwaggerUrl(): string
    {
        return route('swagger.index');
    }
}
