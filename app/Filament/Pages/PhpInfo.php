<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

final class PhpInfo extends Page
{
    #[Override]
    protected static ?string $navigationLabel = 'Server';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Documentation';

    #[Override]
    protected static ?string $slug = 'documentation/phpinfo';

    #[Override]
    protected static ?int $navigationSort = 3;

    #[Override]
    protected string $view = 'core::filament.pages.phpinfo';

    public function mount(): void
    {
        //
    }
}
