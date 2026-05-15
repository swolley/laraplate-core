<?php

declare(strict_types=1);

namespace Modules\Core\Console\View\Components;

use function Termwind\terminal;

use Illuminate\Console\View\Components\Component;
use Illuminate\Console\View\Components\Mutators\EnsureDynamicContentIsHighlighted;
use Illuminate\Console\View\Components\Mutators\EnsureNoPunctuation;
use Illuminate\Console\View\Components\Mutators\EnsureRelativePaths;
use Illuminate\Console\View\TaskResult;
use Illuminate\Support\InteractsWithTime;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Console task line for migrations: name on the left, module badge and status on the right.
 */
final class MigrationTask extends Component
{
    use InteractsWithTime;

    /**
     * @param  (callable(): bool|int)|null  $task
     */
    public function render($description, string $module, $task = null, $verbosity = OutputInterface::VERBOSITY_NORMAL): void
    {
        $description = $this->mutate($description, [
            EnsureDynamicContentIsHighlighted::class,
            EnsureNoPunctuation::class,
            EnsureRelativePaths::class,
        ]);

        $module_badge = sprintf(' %s', $module);
        $description_width = $this->visibleLength($description);
        $module_badge_width = $this->visibleLength($module_badge);

        $this->output->write("  {$description} ", false, $verbosity);

        $start_time = microtime(true);
        $result = TaskResult::Failure->value;

        try {
            $result = ($task ?: static fn (): int => TaskResult::Success->value)();
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $run_time = $task
                ? (' ' . $this->runTimeForHumans($start_time) . ' ')
                : '';

            $status = match ($result) {
                TaskResult::Failure->value => ' FAIL',
                TaskResult::Skipped->value => ' SKIPPED',
                default => ' DONE',
            };

            $run_time_width = $this->visibleLength($run_time);
            $status_width = $this->visibleLength($status);
            $width = min(terminal()->width(), 150);
            $dots = max($width - 2 - $description_width - $run_time_width - $module_badge_width - $status_width, 0);

            $this->output->write(str_repeat('<fg=gray>.</>', $dots), false, $verbosity);
            $this->output->write("<fg=cyan>{$module_badge}</>", false, $verbosity);
            $this->output->write("<fg=gray>{$run_time}</>", false, $verbosity);
            $this->output->writeln(
                match ($result) {
                    TaskResult::Failure->value => '<fg=red;options=bold>FAIL</>',
                    TaskResult::Skipped->value => '<fg=yellow;options=bold>SKIPPED</>',
                    default => '<fg=green;options=bold>DONE</>',
                },
                $verbosity,
            );
        }
    }

    private function visibleLength(?string $value): int
    {
        return mb_strlen(
            preg_replace("/\<[\w=#\/\;,:.&,%?]+\>|\\e\[\d+m/", '$1', $value ?? '') ?? '',
        );
    }
}
