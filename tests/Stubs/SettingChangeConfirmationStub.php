<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Modules\Core\Contracts\ISettingChangeConfirmation;
use Modules\Core\DTOs\SettingChangeWarning;
use Modules\Core\Models\Setting;

final class SettingChangeConfirmationStub implements ISettingChangeConfirmation
{
    public int $confirmedCalls = 0;

    public mixed $confirmedValue = null;

    public function __construct(
        private readonly string $settingName,
        private readonly ?SettingChangeWarning $warning = null,
        private readonly ?string $locked = null,
    ) {}

    public function supports(string $settingName): bool
    {
        return $settingName === $this->settingName;
    }

    public function warn(Setting $setting, mixed $newValue): ?SettingChangeWarning
    {
        return $this->warning;
    }

    public function confirmed(Setting $setting, mixed $newValue): void
    {
        $this->confirmedCalls++;
        $this->confirmedValue = $newValue;
    }

    public function lockedReason(Setting $setting): ?string
    {
        return $this->locked;
    }
}
