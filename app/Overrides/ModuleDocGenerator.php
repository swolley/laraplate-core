<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Mtrajano\LaravelSwagger\Generator;
use Override;

final class ModuleDocGenerator extends Generator
{
    public function __construct($config, private readonly string $module, $routeFilter = null)
    {
        parent::__construct($config, $routeFilter);
    }

    /**
     * @return array<int, ModuleDocRoute>
     */
    #[Override]
    protected function getAppRoutes(): array
    {
        $module = Str::replace('Modules\\', '', $this->module);
        $all_module_routes = routes(true, $module);
        $module_routes = [];

        foreach ($all_module_routes as $route) {
            if ($this->shouldIgnoreRoute($route)) {
                continue;
            }

            if (! $route->wheres) {
                $module_routes[] = new ModuleDocRoute($route);

                continue;
            }

            $parameter_values = [];

            foreach ($route->wheres as $key => $value) {
                if (! is_string($value) && ! is_array($value)) {
                    continue;
                }

                $values = is_string($value) && Str::contains($value, '|')
                    ? explode('|', $value)
                    : (array) $value;

                if ($values !== []) {
                    $parameter_values[$key] = $values;
                }
            }

            if ($parameter_values === []) {
                $module_routes[] = new ModuleDocRoute($route);

                continue;
            }

            foreach ($this->generateCombinations($parameter_values) as $combination) {
                $new_route = clone $route;
                $new_uri = $new_route->uri;

                foreach ($combination as $param => $value) {
                    $new_uri = str_replace('{' . $param . '}', $value, $new_uri);
                    unset($new_route->wheres[$param]);
                }

                $new_route->uri = $new_uri;
                $module_routes[] = new ModuleDocRoute($new_route);
            }
        }

        return $module_routes;
    }

    #[Override]
    protected function generatePath(): void
    {
        parent::generatePath();
        $uri = $this->route->uri();
        $operationId = $this->method . str_replace(['/', '{', '}'], ['-', '', ''], $uri);
        $group = Str::contains($uri, '/app/') ? 'App' : (Str::contains($uri, '/api/') ? 'Api' : 'Others');
        $path_method = &$this->docs['paths'][$this->route->uri()][$this->method];
        $path_method['operationId'] = $operationId;
        $path_method['tags'] = array_unique([$group, Str::replace('Modules\\', '', $this->module)]);

        if ($this->route->uri() === '/up') {
            $path_method['responses']['200']['content'] = [
                'text/html' => [],
            ];
        }
    }

    private function shouldIgnoreRoute(Route $route): bool
    {
        if (! isset($this->config['ignoredRoutes'])) {
            return false;
        }

        $route_name = $route->getName();

        if ($route_name === '' || Str::startsWith($route->uri(), ['_', '/_'])) {
            return true;
        }

        foreach ($this->config['ignoredRoutes'] as $pattern) {
            if (! Str::contains($pattern, '*')) {
                if ($route_name === $pattern) {
                    return true;
                }

                continue;
            }

            $regex = str_replace('\*', '.*', preg_quote((string) $pattern, '/'));

            if (preg_match('/^' . $regex . '$/', (string) $route_name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates all possible combinations of parameter values.
     *
     * @param  array  $parameters  Array of parameters and their possible values
     * @return array Array of all possible combinations
     */
    private function generateCombinations(array $parameters): array
    {
        if ($parameters === []) {
            return [[]];
        }

        $param = key($parameters);
        $values = array_shift($parameters);

        $combinations = [];
        $sub_combinations = $this->generateCombinations($parameters);

        foreach ($values as $value) {
            foreach ($sub_combinations as $sub_combination) {
                $combinations[] = array_merge([$param => $value], $sub_combination);
            }
        }

        return $combinations;
    }
}
