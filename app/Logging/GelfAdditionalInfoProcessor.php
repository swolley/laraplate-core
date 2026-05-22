<?php

declare(strict_types=1);

namespace Modules\Core\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use Override;
use Throwable;

final readonly class GelfAdditionalInfoProcessor implements ProcessorInterface
{
    /** @var array<string, string|null> */
    private static array $moduleVersionCache = [];

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

    private PsrLogMessageProcessor $psrLogMessageProcessor;

    private GelfErrorFingerprintResolver $fingerprint_resolver;

    public function __construct(
        private ?string $channel = null,
        ?GelfErrorFingerprintResolver $fingerprint_resolver = null,
    ) {
        $this->psrLogMessageProcessor = new PsrLogMessageProcessor(removeUsedContextFields: true);
        $this->fingerprint_resolver = $fingerprint_resolver ?? new GelfErrorFingerprintResolver;
    }

    #[Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $record = $this->psrLogMessageProcessor->__invoke($record);

        $module = $this->resolveModule($record);

        $extra = [
            'app_name' => config('app.name'),
            'application_version' => version(),
            'channel' => $this->channel ?? config('logging.default'),
            'module' => $module,
        ];

        $module_version = $this->resolveModuleVersion($module);

        if ($module_version !== null) {
            $extra['module_version'] = $module_version;
        }

        $request_context = $this->resolveRequestContext();

        $extra['error_fingerprint'] = $this->fingerprint_resolver->resolve($record);

        if ($request_context === []) {
            $extra['user'] = 'console';
        }

        $record->extra = array_merge($record->extra, $extra, $request_context);

        return $record;
    }

    private function resolveModule(LogRecord $record): string
    {
        $from_exception = $this->resolveModuleFromException($record->context);

        if ($from_exception !== null) {
            return $from_exception;
        }

        return $this->resolveModuleFromTrace();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveModuleFromException(array $context): ?string
    {
        if (! array_key_exists('exception', $context)) {
            return null;
        }

        $exception = $context['exception'];

        if ($exception instanceof Throwable) {
            return file_module($exception->getFile());
        }

        if (is_array($exception) && isset($exception['file']) && is_string($exception['file'])) {
            return file_module($exception['file']);
        }

        return null;
    }

    private function resolveModuleFromTrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        array_shift($trace);
        array_shift($trace);

        foreach ($trace as $frame) {
            if ($this->shouldSkipTraceFrame($frame)) {
                continue;
            }

            if (isset($frame['class']) && is_string($frame['class'])) {
                return class_module($frame['class']);
            }

            if (isset($frame['file']) && is_string($frame['file'])) {
                return file_module($frame['file']);
            }
        }

        return 'App';
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

    private function resolveModuleVersion(string $module): ?string
    {
        if ($module === 'App') {
            return null;
        }

        if (array_key_exists($module, self::$moduleVersionCache)) {
            return self::$moduleVersionCache[$module];
        }

        $composer_path = module_path($module, 'composer.json');

        if (! is_file($composer_path)) {
            self::$moduleVersionCache[$module] = null;

            return null;
        }

        $composer = json_decode((string) file_get_contents($composer_path), true);
        $resolved = is_array($composer) && isset($composer['version']) && is_string($composer['version'])
            ? $composer['version']
            : null;

        self::$moduleVersionCache[$module] = $resolved;

        return $resolved;
    }

    /**
     * @return array<string, int|string>
     */
    private function resolveRequestContext(): array
    {
        if (! app()->bound(Request::class)) {
            return [];
        }

        $request = app(Request::class);

        if (! $request instanceof Request) {
            return [];
        }

        $route = $request->route();

        if ($route === null) {
            return [];
        }

        $context = [
            'request_url' => $request->fullUrl(),
        ];

        $route_name = $route->getName();

        if (is_string($route_name) && $route_name !== '') {
            $context['request_name'] = $route_name;
        }

        $user_id = $this->resolveSessionUserId($request);

        if ($user_id !== null) {
            $context['user_id'] = $user_id;
        }

        return $context;
    }

    private function resolveSessionUserId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $user_id = Auth::id();

        if (! is_int($user_id) && ! is_string($user_id)) {
            return null;
        }

        return (int) $user_id;
    }
}
