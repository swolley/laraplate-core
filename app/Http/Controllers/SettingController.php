<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request as RequestFacade;
use Modules\Core\Actions\Settings\GetSiteConfigsAction;
use Modules\Core\Actions\Settings\GetTranslationsAction;
use Modules\Core\Actions\Settings\GetVersionInfoAction;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\TranslationsRequest;

final class SettingController extends Controller
{
    public function __construct(
        private readonly GetTranslationsAction $getTranslationsAction,
        private readonly GetSiteConfigsAction $getSiteConfigsAction,
        private readonly GetVersionInfoAction $getVersionInfoAction,
    ) {
    }

    /**
     * @route-comment
     * Route(path: 'app/translations/{lang?}', name: 'core.info.translations', methods: [GET, HEAD], middleware: [info])
     */
    public function getTranslations(TranslationsRequest $request, ?string $lang = null): \Illuminate\Http\JsonResponse
    {
        $translations = Cache::tags(Cache::getCacheTags('translations'))->rememberForever(RequestFacade::route()->getName() . $lang . json_encode($request->validated()), function () use ($lang): array {
            $selectedLang = (! in_array($lang, [null, '', '0'], true)) ? mb_substr($lang, 0, 2) : null;

            return ($this->getTranslationsAction)($selectedLang);
        });

        return new ResponseBuilder($request)
            ->setData($translations)
            ->json();
    }

    /**
     * @route-comment
     * Route(path: 'app/configs', name: 'core.info.getSiteConfigs', methods: [GET, HEAD], middleware: [info])
     */
    public function getSiteConfigs(Request $request): \Illuminate\Http\JsonResponse
    {
        $settings = Cache::tags(Cache::getCacheTags('settings'))->rememberForever(
            RequestFacade::route()->getName(),
            fn (): array => ($this->getSiteConfigsAction)(),
        );

        return new ResponseBuilder($request)
            ->setData($settings)
            ->json();
    }

    /**
     * @route-comment
     * Route(path: 'app/info', name: 'core.info.siteInfo', methods: [GET, HEAD], middleware: [info])
     */
    public function siteInfo(Request $request): \Illuminate\Http\JsonResponse
    {
        return new ResponseBuilder($request)
            ->setData([
                'name' => config('app.name'),
            ] + ($this->getVersionInfoAction)())
            ->json();
    }
}
