<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages\Documentation;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Swagger extends Page
{
    protected static ?string $navigationLabel = 'API Documentation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected static string|UnitEnum|null $navigationGroup = 'Documentation';

    protected static ?string $slug = 'documentation/swagger';

    protected static ?int $navigationSort = 2;

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

