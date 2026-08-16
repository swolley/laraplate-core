<?php

declare(strict_types=1);

namespace Modules\Core\Logging;

use Modules\Core\Logging\Fingerprint\Fingerprinter;
use Modules\Core\Logging\Fingerprint\FingerprintNormalizer;
use Monolog\LogRecord;
use Throwable;

/**
 * The in-process frame resolver: from a real {@see Throwable} (or a flattened
 * exception array / a plain log record) it recovers the fingerprint parts and
 * hashes them through the shared {@see Fingerprinter}. The message normalization
 * lives in the Core fingerprint chain, so an error fingerprinted here and the
 * same error received by SAO from a payload yield one key.
 *
 * The line number is intentionally absent from the hash (a refactor that shifts
 * lines must not fork the group); it is retained as metadata by the GELF
 * processor, not by the fingerprint.
 */
final class GelfErrorFingerprintResolver
{
    /**
     * @var list<string>
     */
    private const array SKIP_CLASS_PARTIALS = [
        'Monolog\\',
        'Illuminate\\Log\\',
        'Illuminate\\Support\\Facades\\',
        'Illuminate\\Foundation\\Bootstrap\\',
        'Modules\\Core\\Logging\\',
        'Psr\\Log\\',
        'PHPUnit\\',
        'Pest\\',
    ];

    /**
     * @var list<string>
     */
    private const array SKIP_FUNCTIONS = [
        'call_user_func',
        'call_user_func_array',
    ];

    private readonly Fingerprinter $fingerprinter;

    public function __construct(?Fingerprinter $fingerprinter = null)
    {
        $this->fingerprinter = $fingerprinter ?? new Fingerprinter(FingerprintNormalizer::default());
    }

    public function resolve(LogRecord $record): string
    {
        if (array_key_exists('exception', $record->context)) {
            $signature = $this->resolveExceptionSignature($record->context['exception']);

            if ($signature !== null) {
                return $this->fingerprinter->hash(
                    'exception',
                    $signature['module'],
                    $signature['class'],
                    $signature['file'],
                    $signature['function'],
                    $signature['message'],
                );
            }
        }

        $caller = $this->resolveCallerFrame();

        return $this->fingerprinter->hash(
            'log',
            $caller['module'],
            $caller['class'],
            $caller['file'],
            $caller['function'],
            $record->message,
        );
    }

    /**
     * @return array{module: string, class: string, file: string, function: string, message: string}|null
     */
    private function resolveExceptionSignature(mixed $exception): ?array
    {
        if ($exception instanceof Throwable) {
            $exception = $this->rootCause($exception);

            return [
                'module' => file_module($exception->getFile()),
                'class' => $exception::class,
                'file' => $this->normalizePath($exception->getFile()),
                'function' => $this->exceptionFunction($exception),
                'message' => $exception->getMessage(),
            ];
        }

        if (! is_array($exception)) {
            return null;
        }

        $class = $exception['class'] ?? null;
        $file = $exception['file'] ?? null;
        $message = $exception['message'] ?? null;

        if (! is_string($class) || ! is_string($file) || ! is_string($message)) {
            return null;
        }

        return [
            'module' => file_module($file),
            'class' => $class,
            'file' => $this->normalizePath($file),
            'function' => is_string($exception['function'] ?? null) ? $exception['function'] : '',
            'message' => $message,
        ];
    }

    private function exceptionFunction(Throwable $exception): string
    {
        $frame = $exception->getTrace()[0] ?? [];

        return isset($frame['function']) && is_string($frame['function']) ? $frame['function'] : '';
    }

    private function rootCause(Throwable $exception): Throwable
    {
        while ($exception->getPrevious() !== null) {
            $exception = $exception->getPrevious();
        }

        return $exception;
    }

    /**
     * @return array{module: string, class: string, file: string, function: string}
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
            $function = isset($frame['function']) && is_string($frame['function'])
                ? $frame['function']
                : '';

            if ($class !== '' || $file !== '') {
                return [
                    'module' => $raw_file !== '' ? file_module($raw_file) : 'App',
                    'class' => $class,
                    'file' => $file,
                    'function' => $function,
                ];
            }
        }

        return [
            'module' => 'App',
            'class' => '',
            'file' => '',
            'function' => '',
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
            return str_replace('\\', '/', mb_substr($path, mb_strlen($base_path)));
        }

        return str_replace('\\', '/', $path);
    }
}
