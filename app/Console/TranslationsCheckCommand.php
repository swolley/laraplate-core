<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Core\Overrides\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class TranslationsCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:check-translations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify languages, labels coherence and sort all translations keys <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->output->writeln(['', 'Sorting translations', '']);
        $languages = translations(true);
        $translations = [];
        $to_be_ignored_files = [];

        foreach ($languages as $lang) {
            $file = $lang . DIRECTORY_SEPARATOR . '*.php';
            $files = glob($file);

            foreach ($files as $file) {
                $this->sortTranslations($translations, $lang, $file, $to_be_ignored_files);
            }
        }

        $this->checkLabels($translations, $to_be_ignored_files);

        $this->output->writeln('');

        Cache::tags(Cache::getCacheTags('translations'))->flush();

        return BaseCommand::SUCCESS;
    }

    private function compactTranslations(array &$translations, ?string $subgroup = null): array
    {
        $mapped = [];

        foreach (array_keys($translations) as $key) {
            $fullpath = in_array($subgroup, [null, '', '0'], true) ? $key : sprintf('%s.%s', $subgroup, $key);

            if (gettype($translations[$key]) === 'string') {
                $mapped[] = $fullpath;
            } else {
                $mapped = array_merge($mapped, $this->compactTranslations($translations[$key], $fullpath));
            }
        }

        return $mapped;
    }

    private function sortTranslations(array &$translations, string $lang, string $file, array &$to_be_ignored_files): void
    {
        $langname = explode(DIRECTORY_SEPARATOR, $lang);
        $langname = array_pop($langname);

        $contains_requires = Str::contains(file_get_contents($file), 'require(__DIR__');
        $required = require $file;
        $file_identifier = str_replace(DIRECTORY_SEPARATOR . $langname . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR, $file);

        if (! array_key_exists($file_identifier, $translations)) {
            $translations[$file_identifier] = [];
        }

        $translations[$file_identifier][$langname] = $this->compactTranslations($required);

        if ($contains_requires) {
            $to_be_ignored_files[] = $file;
            $this->output->writeln(sprintf('<comment>%s imports another language</comment>', $file));

            return;
        }

        $stringified = var_export($required, true);
        $imported = var_export(array_sort_keys($required), true);

        if ($stringified === $imported) {
            $this->output->writeln(sprintf('<info>%s is ok</info>', $file));

            return;
        }

        $replaced = mb_ltrim((string) preg_replace(["/(=> \n\s+)?array \(/", "/\)/"], ['=> [', ']'], $imported), '=> ');
        file_put_contents($file, "<?php\n\nreturn " . $replaced . ';');
        $this->output->writeln(sprintf('<info>%s has been sorted</info>', $file));
    }

    private function checkLabels(array &$translations, array &$to_be_ignored_files): void
    {
        $this->output->writeln(['', 'Checking labels', '']);
        $all_languages = [];

        // prima accumulo tutte le lingue
        foreach ($translations as $file => $langs) {
            $all_labels = [];

            // prima accumulo tutte le labels
            foreach ($langs as $lang => $labels) {
                if (! in_array($lang, $all_languages, true)) {
                    $all_languages[] = $lang;
                }

                foreach ($labels as $label) {
                    if (! in_array($label, $all_labels, true)) {
                        $all_labels[] = $label;
                    }
                }
            }

            // poi controllo in quale file mancano labels
            foreach ($langs as $lang => $labels) {
                $realfile = str_replace('*', $lang, $file);

                if (in_array($realfile, $to_be_ignored_files, true)) {
                    $this->output->writeln(sprintf('<comment>%s imports another language</comment>', $realfile));

                    continue;
                }

                $diff = array_diff($all_labels, $labels);

                if ($diff === []) {
                    $this->output->writeln(sprintf('<info>%s labels are ok</info>', $realfile));
                } else {
                    $joined_labels = implode(', ', $diff);
                    $this->output->writeln(sprintf('<error>%s is missing the current labels: %s</error>', $realfile, $joined_labels));
                }
            }
        }

        $this->output->writeln(['', 'Checking languages', '']);
        $any_missing_file = false;

        // poi controllo di quale file manca la lingua
        foreach ($translations as $file => $langs) {
            $diff = array_diff($all_languages, array_keys($langs));

            if ($diff !== []) {
                $any_missing_file = true;

                foreach ($diff as $lang) {
                    $realfile = str_replace('*', $lang, $file);
                    $this->output->writeln(sprintf('<error>%s is missing</error>', $realfile));
                }
            }
        }

        if (! $any_missing_file) {
            $this->output->writeln('<info>All languages are correctly translated</info>');
        }
    }
}
