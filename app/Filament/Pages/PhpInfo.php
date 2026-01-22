<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class PhpInfo extends Page
{
    protected static ?string $navigationLabel = 'Server';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    protected static string|UnitEnum|null $navigationGroup = 'Documentation';

    protected static ?string $slug = 'documentation/phpinfo';

    protected static ?int $navigationSort = 3;

    protected string $view = 'core::filament.pages.phpinfo';

    public function mount(): void
    {
        //
    }
}
