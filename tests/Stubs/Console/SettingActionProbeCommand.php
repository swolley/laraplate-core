<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Illuminate\Console\Command;

/**
 * Records the arguments a setting action passed, so tests can prove each placeholder
 * arrived as exactly one argument.
 */
final class SettingActionProbeCommand extends Command
{
    /**
     * @var list<array{name: string, flag: ?string}>
     */
    public static array $calls = [];

    protected $signature = 'laraplate:setting-action-probe {name} {--flag=} {--fail}';

    protected $description = 'test';

    public function handle(): int
    {
        $flag = $this->option('flag');

        self::$calls[] = [
            'name' => (string) $this->argument('name'),
            'flag' => is_string($flag) ? $flag : null,
        ];

        $this->line('probe received ' . $this->argument('name'));

        return $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }
}
