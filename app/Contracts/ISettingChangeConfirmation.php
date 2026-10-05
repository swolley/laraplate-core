<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Data\SettingChangeWarning;
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
     *
     * This is a UI-level lock of the settings form, not a domain invariant: the API,
     * `Setting::save()` and approved pending modifications are not blocked by it. A module that
     * needs a real guard enforces it in its own service.
     */
    public function lockedReason(Setting $setting): ?string;
}
