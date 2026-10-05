<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\SettingChangeWarning;
use Modules\Core\Models\Setting;

/**
 * Lets a module ask for a confirmation, or lock a setting, when its value is edited from the
 * settings page. Register implementations in `SettingChangeConfirmations`.
 */
interface ISettingChangeConfirmation
{
    public function supports(string $settingName): bool;

    /**
     * The warning to confirm before the change is saved; null when no confirmation is needed.
     */
    public function warn(Setting $setting, mixed $newValue): ?SettingChangeWarning;

    /**
     * Runs once after the confirmed value has been saved.
     */
    public function confirmed(Setting $setting, mixed $newValue): void;

    /**
     * A non-null reason disables the value field and is shown as its helper text.
     */
    public function lockedReason(Setting $setting): ?string;
}
