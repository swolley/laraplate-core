<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Modules\Core\Overrides\Command;
use Modules\Core\Performance\CachegrindParser;
use Modules\Core\Performance\CachegrindSummary;
use Modules\Core\Performance\EndpointProfiler;
use Override;
use Symfony\Component\Process\Process;

/**
 * Produces or summarizes an Xdebug flame profile of an endpoint, ranking the
 * hottest functions by self cost so bottlenecks are pinpointed rather than
 * guessed.
 *
 * Two modes:
 *   perf:profile GET:/api/v1/... --user=1   # spawn a profiled child, summarize
 *   perf:profile --file=cachegrind.out.123  # summarize an existing profile
 */
final class PerfProfileCommand extends Command
{
    #[Override]
    protected $signature = 'perf:profile
        {endpoint? : METHOD:URI to profile under Xdebug, e.g. GET:/app/about}
        {--file= : Summarize an existing cachegrind file instead of producing one}
        {--user= : Authenticate profiled requests as this user id}
        {--count=15 : Requests to run under the profiler (amortizes boot cost)}
        {--limit=30 : Number of top functions to display}
        {--all : Include framework/vendor noise (autoload, PDO connect, tinker)}
        {--child : Internal: run the scenario inside the profiled child process}';

    #[Override]
    protected $description = 'Xdebug-profile an endpoint and rank the hottest functions by self time <fg=green>(⚡ Modules\Core)</fg=green>';

    public function handle(EndpointProfiler $profiler, AuthFactory $auth, CachegrindParser $parser): int
    {
        $file = $this->option('file');

        if (is_string($file) && $file !== '') {
            if (! is_file($file)) {
                $this->error(sprintf('Cachegrind file not found: %s', $file));

                return self::FAILURE;
            }

            $this->renderSummary($parser->summarize($file, $this->limit()));

            return self::SUCCESS;
        }

        $endpoint = $this->argument('endpoint');

        if (! is_string($endpoint) || $endpoint === '') {
            $this->error('Provide an endpoint (METHOD:URI) to profile, or --file=<cachegrind> to summarize an existing profile.');

            return self::FAILURE;
        }

        $parsed = $this->parseSpec($endpoint);

        if ($parsed === null) {
            $this->error(sprintf("Invalid endpoint spec '%s'. Expected METHOD:URI.", $endpoint));

            return self::FAILURE;
        }

        [$method, $uri] = $parsed;

        if ((bool) $this->option('child')) {
            $user = $this->resolveUser($auth);
            $profiler->profile($method, $uri, max(1, (int) $this->option('count')), 2, $user);

            return self::SUCCESS;
        }

        return $this->spawnAndSummarize($endpoint, $parser);
    }

    private function spawnAndSummarize(string $endpoint, CachegrindParser $parser): int
    {
        if (! extension_loaded('xdebug')) {
            $this->error('Xdebug is required to produce a profile. Install/enable it, or use --file to summarize an existing cachegrind.');

            return self::FAILURE;
        }

        $dir = sprintf('%s/perf-profile-%d-%s', sys_get_temp_dir(), getmypid(), bin2hex(random_bytes(4)));

        if (! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error(sprintf('Unable to create profile output directory: %s', $dir));

            return self::FAILURE;
        }

        $arguments = [
            PHP_BINARY,
            '-d', 'xdebug.mode=profile',
            '-d', 'xdebug.start_with_request=yes',
            '-d', 'xdebug.output_dir=' . $dir,
            '-d', 'xdebug.use_compression=0',
            base_path('artisan'),
            'perf:profile',
            '--child',
            $endpoint,
            '--count=' . (int) $this->option('count'),
        ];

        $user = $this->option('user');

        if (is_string($user) && $user !== '') {
            $arguments[] = '--user=' . $user;
        }

        $this->info(sprintf('Profiling %s (%d requests) under Xdebug…', $endpoint, (int) $this->option('count')));

        $process = new Process($arguments, base_path(), timeout: 600.0);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Profiled child process failed:');
            $this->line($process->getErrorOutput());
            $this->cleanup($dir);

            return self::FAILURE;
        }

        $cachegrind = $this->newestCachegrind($dir);

        if ($cachegrind === null) {
            $this->error('No cachegrind output was produced. Is Xdebug profile mode available?');
            $this->cleanup($dir);

            return self::FAILURE;
        }

        $this->renderSummary($parser->summarize($cachegrind, $this->limit()));
        $this->cleanup($dir);

        return self::SUCCESS;
    }

    private function renderSummary(CachegrindSummary $summary): void
    {
        $include_noise = (bool) $this->option('all');
        $rows = [];

        foreach ($summary->functions as $function) {
            if (! $include_noise && $this->isNoise($function->name)) {
                continue;
            }

            $rows[] = [
                sprintf('%.1f%%', $function->percent),
                number_format($function->self),
                $function->name,
            ];
        }

        $this->table(['self %', 'self cost', 'function'], $rows);
        $this->line(sprintf('<fg=gray>total self cost: %s units</>', number_format($summary->totalSelf)));
    }

    private function isNoise(string $name): bool
    {
        return preg_match(
            '/tinker|password_hash|curl_|ComposerAutoloader|ClassLoader\.php|PDO::connect|Composer\\\\|::include|::require/i',
            $name,
        ) === 1;
    }

    private function newestCachegrind(string $dir): ?string
    {
        $files = glob($dir . '/cachegrind.out.*');

        if ($files === false || $files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function cleanup(string $dir): void
    {
        $files = glob($dir . '/*');

        foreach ($files === false ? [] : $files as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }

    private function resolveUser(AuthFactory $auth): ?Authenticatable
    {
        $user_id = $this->option('user');

        if (! is_string($user_id) || $user_id === '') {
            return null;
        }

        $guard = $auth->guard();

        if (! method_exists($guard, 'getProvider')) {
            return null;
        }

        return $guard->getProvider()->retrieveById($user_id);
    }

    private function limit(): int
    {
        return max(1, (int) $this->option('limit'));
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function parseSpec(string $spec): ?array
    {
        if (! str_contains($spec, ':')) {
            return null;
        }

        [$method, $uri] = explode(':', $spec, 2);
        $method = mb_trim($method);
        $uri = mb_trim($uri);

        if ($method === '' || $uri === '' || preg_match('/^[A-Za-z]+$/', $method) !== 1) {
            return null;
        }

        return [$method, $uri];
    }
}
