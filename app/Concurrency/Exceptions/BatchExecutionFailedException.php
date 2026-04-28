<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency\Exceptions;

use Modules\Core\Concurrency\BatchOutcome;
use RuntimeException;

/**
 * Thrown by ParallelTaskRunner when ErrorPolicy::FailFast is in effect and a
 * task fails. The runner stops scheduling new tasks, lets in-flight workers
 * drain, then throws this exception with the offending outcome attached.
 */
final class BatchExecutionFailedException extends RuntimeException
{
    public function __construct(public readonly BatchOutcome $outcome)
    {
        $error = $outcome->error;
        $message = sprintf(
            'Task "%s" failed: %s in %s on line %d',
            $outcome->taskId,
            $error['message'] ?? 'unknown error',
            $error['file'] ?? '?',
            (int) ($error['line'] ?? 0),
        );

        parent::__construct($message);
    }

    public function trace(): string
    {
        return $this->outcome->error['trace'] ?? '';
    }
}
