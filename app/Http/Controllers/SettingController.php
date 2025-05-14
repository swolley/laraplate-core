<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\TranslationsRequest;
use Modules\Core\Models\Setting;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class SettingController extends Controller
{
    /**
     * @route-comment
     * Route(path: 'app/translations/{lang?}', name: 'core.info.translations', methods: [GET, HEAD], middleware: [info])
     */
    public function getTranslations(TranslationsRequest $request, ?string $lang = null): HttpFoundationResponse
    {
        if ($lang !== null && $lang !== '' && $lang !== '0') {
            $lang = mb_substr($lang, 0, 2);
        }

        $translations = Cache::tags([config('app.name')])->remember(RequestFacade::route()->getName() . $lang . json_encode($request->validated()), config('cache.duration'), function () use ($lang): array {
            $default_locale = App::getLocale();
            $languages = self::getLanguages($default_locale);

            $translations = [];

            foreach ($languages as $language) {
                $short_name = explode(DIRECTORY_SEPARATOR, $language);
                $short_name = array_pop($short_name);

                if ($lang && $short_name !== $lang) {
                    continue;
                }

                $translations[$short_name] = $this->mergeLanguageFiles($language);

                // key always exists because $languages is sorted with $default_locale as the first item
                if ($short_name !== $default_locale && array_key_exists($default_locale, $translations)) {
                    $translations[$short_name] = array_merge($translations[$default_locale], $translations[$short_name]);
                }
            }

            if ($lang !== null && $lang !== '' && $lang !== '0') {
                return head($translations);
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
        return new ResponseBuilder($request)
            ->setData([
                'name' => config('app.name'),
            ] + $this->getVersion())
            ->json();
    }

    public function getVersion(): array
    {
        $hash = $this->getCurrentCommitHash();

        return [
            'version' => $this->getCurrentPackageVersion(),
            'commit' => $hash,
            'date' => $this->getCommitDate($hash),
        ];
    }

    private function getCurrentPackageVersion(): ?string
    {
        return $this->getCurrentTag() ?? version();
    }

    private function getGitDirectory(): string
    {
        return base_path() . '/.git';
    }

    private function getCurrentCommitHash(): ?string
    {
        $git_dir = $this->getGitDirectory();

        if (! is_dir($git_dir)) {
            return null;
        }

        $head_file = $git_dir . '/HEAD';

        if (! file_exists($head_file)) {
            return null;
        }

        $head_content = mb_trim(file_get_contents($head_file));

        // Se HEAD punta direttamente a un commit
        if (preg_match('/^[0-9a-f]{40}$/', $head_content)) {
            return $head_content;
        }

        // Se HEAD punta a un branch
        if (preg_match('/^ref: refs\/heads\/(.+)$/', $head_content, $matches)) {
            $branch_file = $git_dir . '/refs/heads/' . $matches[1];

            if (file_exists($branch_file)) {
                return mb_trim(file_get_contents($branch_file));
            }
        }

        return null;
    }

    private function getCurrentTag(): ?string
    {
        $git_dir = $this->getGitDirectory();

        if (! is_dir($git_dir)) {
            return null;
        }

        // Prima otteniamo l'hash del commit corrente
        $current_commit = $this->getCurrentCommitHash();

        if ($current_commit === null || $current_commit === '' || $current_commit === '0') {
            return null;
        }

        // Leggiamo tutti i file nella directory dei tag
        $tags_dir = $git_dir . '/refs/tags';

        if (! is_dir($tags_dir)) {
            return null;
        }

        $tags = scandir($tags_dir);
        $last_tag = null;
        $last_tag_version = '0.0.0';

        foreach ($tags as $tag) {
            if ($tag === '.') {
                continue;
            }

            if ($tag === '..') {
                continue;
            }
            $tag_file = $tags_dir . '/' . $tag;

            if (! is_file($tag_file)) {
                continue;
            }

            $tag_commit = mb_trim(file_get_contents($tag_file));

            // Se il tag punta direttamente a un commit
            if ($tag_commit === $current_commit) {
                return $tag;
            }

            // Se il tag è un "tag object" (contiene metadata)
            if (file_exists($git_dir . '/objects/' . mb_substr($tag_commit, 0, 2) . '/' . mb_substr($tag_commit, 2))) {
                $tag_content = file_get_contents($git_dir . '/objects/' . mb_substr($tag_commit, 0, 2) . '/' . mb_substr($tag_commit, 2));

                if (mb_strpos($tag_content, $current_commit) !== false) {
                    return $tag;
                }
            }

            // Rimuoviamo il prefisso 'v' per il confronto delle versioni
            $tag_version = mb_ltrim($tag, 'v');

            if (version_compare($tag_version, $last_tag_version, '>')) {
                $last_tag = $tag;
                $last_tag_version = $tag_version;
            }
        }

        return $last_tag;
    }

    private function getCommitDate(string $commit_hash): ?string
    {
        $git_dir = $this->getGitDirectory();

        if (! is_dir($git_dir)) {
            return null;
        }

        $object_path = $git_dir . '/objects/' . mb_substr($commit_hash, 0, 2) . '/' . mb_substr($commit_hash, 2);

        if (! file_exists($object_path)) {
            return null;
        }

        $commit_content = file_get_contents($object_path);

        if ($commit_content === false) {
            return null;
        }

        // Decomprimiamo il contenuto del commit
        $commit_content = gzuncompress($commit_content);

        if ($commit_content === false) {
            return null;
        }

        // Cerchiamo la riga che contiene la data del commit
        if (preg_match('/committer .*? (\d+) ([\+\-]\d{4})/', $commit_content, $matches)) {
            $timestamp = (int) $matches[1];

            return date('Y-m-d H:i:s', $timestamp);
        }

        return null;
    }

    private function getLanguages(string $default_locale): array
    {
        $languages = translations(true, true);

        usort($languages, function ($a, $b) use ($default_locale): int {
            if (Str::endsWith($a, DIRECTORY_SEPARATOR . $default_locale)) {
                return -1;
            }

            if (Str::endsWith($b, DIRECTORY_SEPARATOR . $default_locale)) {
                return 1;
            }

            return $a <=> $b;
        });

        return $languages;
    }

    private function mergeLanguageFiles(string $language): array
    {
        $translations = [];

        /** @var array<int,string> $files */
        $files = glob($language . '/*.php');

        foreach ($files as $file) {
            $contents = include $file;
            $translations[basename($file, '.php')] = $contents;
        }

        return Arr::dot($translations);
    }
}
