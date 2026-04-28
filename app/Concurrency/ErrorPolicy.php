<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency;

/**
 * Behaviour adopted by the runner when a task fails.
 */
enum ErrorPolicy: string
{
    /**
     * Stop scheduling new tasks at the first failure and throw a
     * BatchExecutionFailedException once the in-flight workers have drained.
     * In-flight workers are allowed to finish to avoid orphan processes.
     */
    case FailFast = 'fail_fast';

    /**
     * Keep running. Failures are collected in BatchSummary::$failures.
     * No exception is thrown by the runner.
     */
    case ContinueOnError = 'continue_on_error';
}
