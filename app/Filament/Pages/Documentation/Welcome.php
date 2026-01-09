<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages\Documentation;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Services\Docs\ModuleInfoService;
use UnitEnum;

final class Welcome extends Page
{
    protected static ?string $navigationLabel = 'Welcome';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Documentation';

    protected static ?string $slug = 'documentation/welcome';

    protected static ?int $navigationSort = 1;

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

