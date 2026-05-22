<?php

declare(strict_types=1);

namespace Modules\Core\Logging;

use Monolog\LogRecord;
use Throwable;

final class GelfErrorFingerprintResolver
{
    /** @var list<string> */
    private const SKIP_CLASS_PARTIALS = [
        'Monolog\\',
        'Illuminate\\Log\\',
        'Illuminate\\Support\\Facades\\',
        'Illuminate\\Foundation\\Bootstrap\\',
        'Modules\\Core\\Logging\\',
        'Psr\\Log\\',
        'PHPUnit\\',
        'Pest\\',
    ];

    private const SKIP_FUNCTIONS = [
        'call_user_func',
        'call_user_func_array',
    ];

    public function resolve(LogRecord $record): string
    {
        if (array_key_exists('exception', $record->context)) {
            $signature = $this->resolveExceptionSignature($record->context['exception']);

            if ($signature !== null) {
                return $this->hash([
                    'exception',
                    $signature['module'],
                    $signature['class'],
                    $signature['file'],
                    (string) $signature['line'],
                    $signature['message'],
                ]);
            }
        }

        $caller = $this->resolveCallerFrame();

        return $this->hash([
            'log',
            $caller['module'],
            $caller['class'],
            $caller['file'],
            (string) $caller['line'],
            $this->normalizeMessage($record->message),
        ]);
    }

    /**
     * @return array{module: string, class: string, file: string, line: int, message: string}|null
     */
    private function resolveExceptionSignature(mixed $exception): ?array
    {
        if ($exception instanceof Throwable) {
            $exception = $this->rootCause($exception);
            $file = $this->normalizePath($exception->getFile());

            return [
                'module' => file_module($exception->getFile()),
                'class' => $exception::class,
                'file' => $file,
                'line' => $exception->getLine(),
                'message' => $this->normalizeMessage($exception->getMessage()),
            ];
        }

        if (! is_array($exception)) {
            return null;
        }

        $class = $exception['class'] ?? null;
        $file = $exception['file'] ?? null;
        $line = $exception['line'] ?? null;
        $message = $exception['message'] ?? null;

        if (! is_string($class) || ! is_string($file) || ! is_numeric($line) || ! is_string($message)) {
            return null;
        }

        $normalized_file = $this->normalizePath($file);

        return [
            'module' => file_module($file),
            'class' => $class,
            'file' => $normalized_file,
            'line' => (int) $line,
            'message' => $this->normalizeMessage($message),
        ];
    }

    private function rootCause(Throwable $exception): Throwable
    {
        while ($exception->getPrevious() !== null) {
            $exception = $exception->getPrevious();
        }

        return $exception;
    }

    /**
     * @return array{module: string, class: string, file: string, line: int}
     */
    private function resolveCallerFrame(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        array_shift($trace);
        array_shift($trace);

        foreach ($trace as $frame) {
            if ($this->shouldSkipTraceFrame($frame)) {
                continue;
            }

            $class = isset($frame['class']) && is_string($frame['class'])
                ? $frame['class']
                : '';
            $raw_file = isset($frame['file']) && is_string($frame['file'])
                ? $frame['file']
                : '';
            $file = $raw_file !== ''
                ? $this->normalizePath($raw_file)
                : '';
            $line = isset($frame['line']) && is_int($frame['line'])
                ? $frame['line']
                : 0;

            if ($class !== '' || $file !== '') {
                return [
                    'module' => $raw_file !== '' ? file_module($raw_file) : 'App',
                    'class' => $class,
                    'file' => $file,
                    'line' => $line,
                ];
            }
        }

        return [
            'module' => 'App',
            'class' => '',
            'file' => '',
            'line' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function shouldSkipTraceFrame(array $frame): bool
    {
        if (isset($frame['function']) && in_array($frame['function'], self::SKIP_FUNCTIONS, true)) {
            return true;
        }

        if (! isset($frame['class']) || ! is_string($frame['class'])) {
            return false;
        }

        foreach (self::SKIP_CLASS_PARTIALS as $partial) {
            if (str_contains($frame['class'], $partial)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $base_path = base_path() . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $base_path)) {
            return str_replace('\\', '/', substr($path, strlen($base_path)));
        }

        return str_replace('\\', '/', $path);
    }

    private function normalizeMessage(string $message): string
    {
        $normalized = preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i',
            '{uuid}',
            $message,
        ) ?? $message;

        $normalized = preg_replace(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            '{ip}',
            $normalized,
        ) ?? $normalized;

        $normalized = preg_replace(
            '/\b[0-9a-f]{32,}\b/i',
            '{hex}',
            $normalized,
        ) ?? $normalized;

        $normalized = preg_replace(
            '/\b\d+\b/',
            '{n}',
            $normalized,
        ) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @param  list<string>  $parts
     */
    private function hash(array $parts): string
    {
        return substr(hash('sha256', implode("\0", $parts)), 0, 16);
    }
}
