<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\ContextualValidationException;

/**
 * Builds human-readable validation failure messages for CLI, logs and batch workers.
 */
final class ValidationExceptionDescriber
{
    public static function describe(ValidationException $exception): string
    {
        $lines = self::fieldLines($exception);

        if ($lines === []) {
            return 'Validation failed.';
        }

        $context_suffix = self::formatContextSuffix($exception);

        if (count($lines) === 1) {
            return 'Validation failed for field ' . $lines[0] . $context_suffix;
        }

        return "Validation failed:\n- " . implode("\n- ", $lines) . $context_suffix;
    }

    /**
     * @return list<string>
     */
    private static function fieldLines(ValidationException $exception): array
    {
        $data = method_exists($exception->validator, 'getData') ? $exception->validator->getData() : [];
        $lines = [];

        foreach ($exception->errors() as $field => $messages) {
            $resolved_messages = array_map(
                static fn (string $message): string => self::resolveMessage($message, (string) $field, $exception),
                $messages,
            );

            $lines[] = sprintf(
                '"%s": %s (value: %s)',
                $field,
                implode(', ', $resolved_messages),
                self::formatValue($data[$field] ?? 'N/A'),
            );
        }

        return $lines;
    }

    private static function resolveMessage(string $message, string $field, ValidationException $exception): string
    {
        if (! str_starts_with($message, 'validation.')) {
            return $message;
        }

        $attribute = $exception->validator->customAttributes[$field] ?? $field;
        $replace = ['attribute' => $attribute];

        $translated = Lang::get($message, $replace);

        if ($translated !== $message) {
            return $translated;
        }

        return $message;
    }

    private static function formatValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'N/A';
    }

    private static function formatContextSuffix(ValidationException $exception): string
    {
        if (! $exception instanceof ContextualValidationException) {
            return '';
        }

        $context = $exception->context();

        if ($context === []) {
            return '';
        }

        $parts = [];

        foreach (['entity', 'model', 'operation', 'id'] as $key) {
            if (! array_key_exists($key, $context) || $context[$key] === '' || $context[$key] === null) {
                continue;
            }

            $parts[] = $key . '=' . $context[$key];
        }

        if ($parts === []) {
            return '';
        }

        return ' [' . implode(', ', $parts) . ']';
    }
}
