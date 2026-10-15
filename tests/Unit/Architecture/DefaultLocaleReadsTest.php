<?php

declare(strict_types=1);

use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Calls that take `app.locale` as their first argument: `config('app.locale')`, `Config::get('app.locale')`
 * and the config helpers that wrap them.
 *
 * @return list<int> the lines of the calls
 */
function default_locale_reads(string $source): array
{
    $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
    $lines = [];

    foreach ((new NodeFinder)->findInstanceOf($statements, CallLike::class) as $call) {
        if ($call->isFirstClassCallable()) {
            continue;
        }

        $first = $call->getArgs()[0]->value ?? null;

        if ($first instanceof String_ && $first->value === 'app.locale') {
            $lines[] = $call->getStartLine();
        }
    }

    return $lines;
}

it('finds a read of app.locale', function (): void {
    expect(default_locale_reads("<?php\n\$a = config('app.locale');\n\$b = \\Illuminate\\Support\\Facades\\Config::get('app.locale', 'en');\n\$c = ai_config_string('app.locale', 'en');\n\$d = config('app.default_locale');\n"))
        ->toBe([2, 3, 4]);
});

/**
 * `app.locale` is the language of the current request: App::setLocale() rewrites it, and the localization
 * middleware calls it for every user whose language is not the default one. The default locale, which
 * translations fall back to, is LocaleContext::getDefault(); the current one is LocaleContext::get().
 */
it('reads the default locale through LocaleContext, never from app.locale', function (): void {
    $root = dirname(__DIR__, 5);
    $found = [];

    foreach ([...glob("{$root}/Modules/*/app", GLOB_ONLYDIR) ?: [], "{$root}/app"] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (default_locale_reads((string) file_get_contents($file->getPathname())) as $line) {
                $found[] = mb_substr($file->getPathname(), mb_strlen($root) + 1) . ':' . $line;
            }
        }
    }

    expect($found)->toBe([]);
});
