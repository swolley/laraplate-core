<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeding;

use Modules\Core\Seeding\ModuleState;
use Modules\Core\Seeding\ModuleStateResolver;
use Override;

final class FixedModuleStateResolver extends ModuleStateResolver
{
    public function __construct(private readonly ModuleState $state) {}

    #[Override]
    public function for(?string $module): ModuleState
    {
        return $this->state;
    }
}
