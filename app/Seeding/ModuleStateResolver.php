<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Nwidart\Modules\Facades\Module;

/**
 * Non-final by design: {@see \Modules\Core\Tests\Stubs\Seeding\FixedModuleStateResolver}
 * extends this to return a fixed state in tests. Do not seal this class.
 */
class ModuleStateResolver
{
    /**
     * {@see \Nwidart\Modules\FileRepository::scan()} keys `all()`/`allEnabled()`
     * by `strtolower($name)`, but `core_settings.module` stores the module's
     * declared case (e.g. `MES`, `Core`). Comparing the raw module name against
     * those arrays with `array_key_exists()` would never match, misclassifying
     * every real module as absent. `Module::find()` lowercases internally
     * ({@see \Nwidart\Modules\FileRepository::find()}), so it is used here
     * instead of a direct array lookup.
     */
    public function for(?string $module): ModuleState
    {
        if ($module === null || $module === '') {
            return ModuleState::Enabled;
        }

        $found = Module::find($module);

        if ($found === null) {
            return ModuleState::Absent;
        }

        return $found->isEnabled() ? ModuleState::Enabled : ModuleState::Disabled;
    }
}
