<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency;

use Illuminate\Validation\ValidationException;
use Modules\Core\Helpers\ValidationExceptionDescriber;
use Throwable;

/**
 * Result of a single executed task.
 *
 * Always returned (never thrown) to keep the parent–child boundary stable: the
 * child wraps any throwable into a failure outcome and exits 0, so the parent
 * never sees CouldNotManageTask for application-level errors.
 *
 * @phpstan-type ErrorPayload array{
 *     message: string,
 *     class: class-string<Throwable>,
 *     file: string,
 *     line: int,
 *     trace: string,
 * }
 */
final readonly class BatchOutcome
{
    /**
     * @param  ErrorPayload|null  $error
     * @param  int  $queryCount  Queries logged on the worker default connection while running this task
     */
    public function __construct(
        public string $taskId,
        public bool $success,
        public int $unitsProcessed,
        public float $duration,
        public mixed $output = null,
        public ?array $error = null,
        public int $queryCount = 0,
    ) {}

    public static function success(string $taskId, int $units, float $duration, mixed $output = null, int $queryCount = 0): self
    {
        return new self(
            taskId: $taskId,
            success: true,
            unitsProcessed: $units,
            duration: $duration,
            output: $output,
            queryCount: $queryCount,
        );
    }

    public static function failure(string $taskId, int $units, float $duration, Throwable $e, int $queryCount = 0): self
    {
        return new self(
            taskId: $taskId,
            success: false,
            unitsProcessed: $units,
            duration: $duration,
            error: [
                'message' => self::extractMessage($e),
                'class' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ],
            queryCount: $queryCount,
        );
    }

    /**
     * Format the error message, prettifying ValidationException's payload.
     */
    private static function extractMessage(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return ValidationExceptionDescriber::describe($e);
        }

        return $e->getMessage();
    }
}
