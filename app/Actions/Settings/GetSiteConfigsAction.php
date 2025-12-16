<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Settings;

use Closure;
use Illuminate\Support\Collection;
use Modules\Core\Models\Setting;

final readonly class GetSiteConfigsAction
{
    public function __construct(
        private ?Closure $settingsProvider = null,
        private ?Closure $modulesProvider = null,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function __invoke(): array
    {
        $settings = [];

        /** @var Collection<int,Setting>|iterable $rows */
        $rows = $this->settingsProvider instanceof Closure ? ($this->settingsProvider)() : Setting::query()->get();

        foreach ($rows as $setting) {
            $settings[$setting->name] = $setting->value;
        }

        $settings['active_modules'] = $this->modulesProvider instanceof Closure ? ($this->modulesProvider)() : modules();

        return $settings;
    }
}
