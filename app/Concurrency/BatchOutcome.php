<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency;

use Illuminate\Validation\ValidationException;
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
     */
    public function __construct(
        public string $taskId,
        public bool $success,
        public int $unitsProcessed,
        public float $duration,
        public mixed $output = null,
        public ?array $error = null,
    ) {}

    public static function success(string $taskId, int $units, float $duration, mixed $output = null): self
    {
        return new self(
            taskId: $taskId,
            success: true,
            unitsProcessed: $units,
            duration: $duration,
            output: $output,
        );
    }

    public static function failure(string $taskId, int $units, float $duration, Throwable $e): self
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
        );
    }

    /**
     * Format the error message, prettifying ValidationException's payload.
     */
    private static function extractMessage(Throwable $e): string
    {
        if (! $e instanceof ValidationException) {
            return $e->getMessage();
        }

        $errors = $e->errors();
        $failed_field = (string) (array_key_first($errors) ?? 'unknown');
        $failed_messages = $errors[$failed_field] ?? [];
        $data = method_exists($e->validator, 'getData') ? $e->validator->getData() : [];
        $failed_value = $data[$failed_field] ?? 'N/A';

        return sprintf(
            'Validation failed for field "%s": %s (value: %s)',
            $failed_field,
            implode(', ', $failed_messages),
            is_scalar($failed_value) ? (string) $failed_value : 'N/A',
        );
    }
}
