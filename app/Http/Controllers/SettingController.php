<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Modules\Core\Models\Setting;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\ResponseBuilder;
use Illuminate\Support\Facades\Request as RequestFacade;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class SettingController extends Controller
{
    /**
     * @route-comment
     * Route(path: 'app/translations/{lang?}', name: 'core.info.translations', methods: [GET, HEAD], middleware: [info])
     */
    public function getTranslations(Request $request, ?string $lang = null): HttpFoundationResponse
    {
        if ($lang) {
            $lang = mb_substr($lang, 0, 2);
        }

        $translations = Cache::tags([config('app.name')])->remember(RequestFacade::route()->getName() . $lang, config('cache.duration'), function () use ($lang) {
            $languages = translations(true, true);
            $translations = [];

            $default_locale = App::getLocale();
            usort($languages, function ($a, $b) use ($default_locale) {
                if (Str::endsWith($a, DIRECTORY_SEPARATOR . $default_locale)) {
                    return -1;
                }

                if (Str::endsWith($b, DIRECTORY_SEPARATOR . $default_locale)) {
                    return 1;
                }

                return $a <=> $b;
            });

            foreach ($languages as $language) {
                $short_name = explode(DIRECTORY_SEPARATOR, $language);
                $short_name = array_pop($short_name);

                if ($lang && $short_name !== $lang) {
                    continue;
                }

                /** @var array<int,string> $files */
                $files = glob($language . '/*.php');

                foreach ($files as $file) {
                    $contents = include $file;

                    if ($lang) {
                        $translations[basename($file, '.php')] = $contents;
                    } else {
                        $translations[$short_name][basename($file, '.php')] = $contents;
                    }
                }

                $translations[$short_name] = Arr::dot($translations[$short_name]);

                // key always exists because $languages is sorted with $default_locale as the first item
                if ($short_name !== $default_locale && array_key_exists($default_locale, $translations)) {
                    $translations[$short_name] = array_merge($translations[$default_locale], $translations[$short_name]);
                }
            }

            return $translations;
        });

        return new ResponseBuilder($request)
            ->setData($translations)
            ->json();
    }

    /**
     * @route-comment
     * Route(path: 'app/configs', name: 'core.info.getSiteConfigs', methods: [GET, HEAD], middleware: [info])
     */
    public function getSiteConfigs(Request $request): HttpFoundationResponse
    {
        $settings = Cache::tags([config('APP_NAME')])->remember(RequestFacade::route()->getName(), config('cache.duration'), function () {
            $settings = [];

            foreach (Setting::get() as $s) {
                $settings[$s->name] = $s->value;
            }

            $settings['active_modules'] = modules();

            return $settings;
        });

        return new ResponseBuilder($request)
            ->setData($settings)
            ->json();
    }

    /**
     * @route-comment
     * Route(path: 'app/info', name: 'core.info.siteInfo', methods: [GET, HEAD], middleware: [info])
     */
    public function siteInfo(Request $request): HttpFoundationResponse
    {
        $data = [
            'name' => config('app.name'),
            'version' => version(),
        ];

        return new ResponseBuilder($request)
            ->setData($data)
            ->json();
    }
}
