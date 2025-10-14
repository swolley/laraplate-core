<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Routing\Route as LaravelRoute;
use Mtrajano\LaravelSwagger\DataObjects\Route;
use ReflectionClass;
use ReflectionException;

final class ModuleDocRoute extends Route
{
    private readonly LaravelRoute $reflectedRoute;

    /**
     * @throws ReflectionException
     */
    public function __construct(LaravelRoute $route)
    {
        parent::__construct($route);
        $class = new ReflectionClass($this);
        $parent = $class->getParentClass();
        $property = $parent->getProperty('route');
        $this->reflectedRoute = $property->getValue($this);
    }

    public function name(): ?string
    {
        return $this->reflectedRoute->action['as'] ?? null;
    }

    public function group(): string
    {
        $name = $this->name();

        if (in_array($name, [null, '', '0'], true)) {
            return '';
        }
        $exploded = explode('.', $name);

        return array_shift($exploded);
    }
}
