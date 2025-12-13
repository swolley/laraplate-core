<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Settings;

use Illuminate\Support\Facades\App;
use Modules\Core\Services\Translation\TranslationCatalogService;

final class GetTranslationsAction
{
    public function __construct(private readonly TranslationCatalogService $translationService)
    {
    }

    public function __invoke(?string $lang = null): array
    {
        $defaultLocale = App::getLocale();

        return $this->translationService->buildTranslations($lang, $defaultLocale);
    }
}

