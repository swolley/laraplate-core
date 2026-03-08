<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Container\Container;
use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Override;

final class ModuleMultiActivator implements ActivatorInterface
{
    private ?ActivatorInterface $activator = null;

    public function __construct(
        private readonly Container $app,
    ) {}

    #[Override]
    public function enable(Module $module): void
    {
        $this->getActivator()->enable($module);
    }

    #[Override]
    public function disable(Module $module): void
    {
        $this->getActivator()->disable($module);
    }

    #[Override]
    public function hasStatus(Module|string $module, bool $status): bool
    {
        return $this->getActivator()->hasStatus($module, $status);
    }

    #[Override]
    public function setActive(Module $module, bool $active): void
    {
        $this->getActivator()->setActive($module, $active);
    }

    #[Override]
    public function setActiveByName(string $name, bool $active): void
    {
        $this->getActivator()->setActiveByName($name, $active);
    }

    #[Override]
    public function delete(Module $module): void
    {
        $this->getActivator()->delete($module);
    }

    #[Override]
    public function reset(): void
    {
        $this->getActivator()->reset();
    }

    private function getActivator(): ActivatorInterface
    {
        if ($this->activator === null) {
            $this->activator = ModuleDatabaseActivator::checkSettingTable()
                ? new ModuleDatabaseActivator($this->app)
                : new FileActivator($this->app);
        }

        return $this->activator;
    }
}
