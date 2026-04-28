<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency;

use Closure;

/**
 * A unit of work to be executed inside a worker.
 *
 * The work can be expressed either as a closure (executed in the forked child)
 * or as the FQCN of an invokable class resolved through the IoC container.
 * The FQCN form is friendlier to serialization (e.g. when re-using the same
 * task definition across different runtimes such as queue workers).
 *
 * @phpstan-type Runnable Closure|class-string
 */
final readonly class BatchTask
{
    /**
     * @param  string  $id  Stable identifier of the task (used in reporters and outcomes)
     * @param  int  $units  Logical work units this task represents (e.g. records); used by progress reporters
     * @param  Runnable  $run  Closure or FQCN of an invokable class
     * @param  array<int, mixed>  $args  Optional positional arguments passed to the runnable
     */
    public function __construct(
        public string $id,
        public int $units,
        public Closure|string $run,
        public array $args = [],
    ) {}

    /**
     * Execute the task. Called inside the forked child process.
     */
    public function execute(): mixed
    {
        if (is_string($this->run)) {
            $instance = app($this->run);

            return $instance(...$this->args);
        }

        return ($this->run)(...$this->args);
    }
}
