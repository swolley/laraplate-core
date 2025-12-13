<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Settings;

use Illuminate\Support\Collection;
use Modules\Core\Models\Setting;

final class GetSiteConfigsAction
{
    public function __construct(
        private readonly ?\Closure $settingsProvider = null,
        private readonly ?\Closure $modulesProvider = null,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function __invoke(): array
    {
        $settings = [];

        /** @var Collection<int,Setting>|iterable $rows */
        $rows = $this->settingsProvider ? ($this->settingsProvider)() : Setting::query()->get();

        foreach ($rows as $setting) {
            $settings[$setting->name] = $setting->value;
        }

        $settings['active_modules'] = $this->modulesProvider ? ($this->modulesProvider)() : modules();

        return $settings;
    }
}

