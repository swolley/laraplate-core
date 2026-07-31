<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Modules\Core\Overrides\Command;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Laravel\Prompts\table;

/**
 * Resolves a relative URL the way the router does at runtime.
 *
 * `route:list` prints the registered routes sorted by URI, which hides the fact that Laravel
 * matches in registration order with no notion of specificity: a generic `{module}/{entity}`
 * route registered early silently shadows every more specific route of the same shape. This
 * command answers the only question that matters — which route actually handles this URL.
 */
final class RouteCheckCommand extends Command implements PromptsForMissingInput
{
    /**
     * @var list<string>
     */
    private const array METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    #[Override]
    protected $signature = 'route:check
                            {url : Relative URL to resolve, e.g. app/crud/select/ai/conversations}
                            {--method=GET : HTTP method to match the URL against}
                            {--json : Output the matched route as JSON}';

    #[Override]
    protected $description = 'Show which route actually handles a relative URL. <fg=green>(⚡ Modules\Core)</fg=green>';

    /**
     * @return array<string, array{string, string}>
     */
    #[Override]
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'url' => ['Which relative URL should be resolved?', 'app/crud/select/ai/conversations'],
        ];
    }

    public function handle(): int
    {
        $method = mb_strtoupper(mb_trim((string) $this->option('method')));

        if (! in_array($method, self::METHODS, true)) {
            $this->output->error(sprintf(
                'Unsupported HTTP method [%s]. Use one of: %s.',
                $method,
                implode(', ', self::METHODS),
            ));

            return BaseCommand::FAILURE;
        }

        $path = '/' . mb_ltrim(mb_trim((string) $this->argument('url')), '/');

        try {
            $route = Route::getRoutes()->match(Request::create($path, $method));
        } catch (MethodNotAllowedHttpException $exception) {
            $allowed = $exception->getHeaders()['Allow'] ?? '';

            $this->output->error(sprintf(
                'No route matches [%s %s]. The URL is registered for: %s.',
                $method,
                $path,
                $allowed !== '' ? $allowed : 'other methods',
            ));

            return BaseCommand::FAILURE;
        } catch (NotFoundHttpException) {
            $this->output->error(sprintf('No route matches [%s %s].', $method, $path));

            return BaseCommand::FAILURE;
        }

        $data = $this->describe($route, $method, $path);

        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return BaseCommand::SUCCESS;
        }

        table(['Field', 'Value'], $this->rows($data));

        return BaseCommand::SUCCESS;
    }

    /**
     * @return array{request: string, name: ?string, uri: string, methods: list<string>, action: string,
     *     middleware: list<string>, parameters: array<string, mixed>, constraints: array<string, string>, domain: ?string}
     */
    private function describe(RoutingRoute $route, string $method, string $path): array
    {
        $action = $route->getAction('uses');

        return [
            'request' => $method . ' ' . $path,
            'name' => $route->getName(),
            'uri' => '/' . $route->uri(),
            'methods' => array_values($route->methods()),
            'action' => is_string($action) ? $action : 'Closure',
            'middleware' => array_values(array_unique(array_map(
                static fn (mixed $middleware): string => is_string($middleware) ? $middleware : 'Closure',
                $route->gatherMiddleware(),
            ))),
            'parameters' => $route->parameters(),
            'constraints' => $route->wheres,
            'domain' => $route->getDomain(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{0: string, 1: string}>
     */
    private function rows(array $data): array
    {
        $rows = [];

        foreach ($data as $field => $value) {
            $rows[] = [
                ucfirst($field),
                match (true) {
                    $value === null || $value === [] => '-',
                    is_array($value) && array_is_list($value) => implode(', ', $value),
                    is_array($value) => implode(', ', array_map(
                        static fn (string $key, mixed $item): string => $key . '=' . (is_scalar($item) ? (string) $item : '-'),
                        array_keys($value),
                        $value,
                    )),
                    default => (string) $value,
                },
            ];
        }

        return $rows;
    }
}
