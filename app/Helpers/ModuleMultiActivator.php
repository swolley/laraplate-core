<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Container\Container;
use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Override;

final readonly class ModuleMultiActivator implements ActivatorInterface
{
    private ActivatorInterface $activator;

    public function __construct(Container $app)
    {
        $this->activator = ModuleDatabaseActivator::checkSettingTable()
            ? new ModuleDatabaseActivator($app)
            : new FileActivator($app);
    }

    #[Override]
    public function enable(Module $module): void
    {
        $this->activator->enable($module);
    }

    #[Override]
    public function disable(Module $module): void
    {
        $this->activator->disable($module);
    }

    #[Override]
    public function hasStatus(Module $module, bool $status): bool
    {
        return $this->activator->hasStatus($module, $status);
    }

    #[Override]
    public function setActive(Module $module, bool $active): void
    {
        $this->activator->setActive($module, $active);
    }

    #[Override]
    public function setActiveByName(string $name, bool $active): void
    {
        $this->activator->setActiveByName($name, $active);
    }

    #[Override]
    public function delete(Module $module): void
    {
        $this->activator->delete($module);
    }

    #[Override]
    public function reset(): void
    {
        $this->activator->reset();
    }
}
