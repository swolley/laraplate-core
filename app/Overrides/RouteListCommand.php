<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Foundation\Console\RouteListCommand as BaseRouteListCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'route:list')]
final class RouteListCommand extends BaseRouteListCommand
{
    protected $description = 'List all registered routes <fg=green>(⚡ Modules\Core)</fg=green>';

    public function __construct(Router $router)
    {
        parent::__construct($router);
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string, 4?: string|null}>
     */
    #[Override]
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['internals', null, InputOption::VALUE_NONE, 'Only show routes prefixed with app or api'],
        ];
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>|null
     */
    #[Override]
    protected function filterRoute(array $route): ?array
    {
        $route = parent::filterRoute($route);

        if ($route === null || ! $this->option('internals')) {
            return $route;
        }

        return Str::startsWith((string) $route['uri'], ['app', 'api'])
            ? $route
            : null;
    }
}
