<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Services\Docs\ModuleInfoService;
use Override;
use UnitEnum;

final class Welcome extends Page
{
    #[Override]
    protected static ?string $navigationLabel = 'Welcome';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Documentation';

    #[Override]
    protected static ?string $slug = 'documentation/welcome';

    #[Override]
    protected static ?int $navigationSort = 1;

    #[Override]
    protected string $view = 'core::filament.pages.welcome';

    public function mount(): void
    {
        //
    }

    public function getGroupedModules(): array
    {
        $moduleInfoService = resolve(ModuleInfoService::class);

        return $moduleInfoService->groupedModules();
    }

    public function getTranslations(): array
    {
        return translations();
    }
}
