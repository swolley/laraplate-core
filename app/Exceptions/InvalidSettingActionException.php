<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Modules\Core\Models\Setting;
use RuntimeException;

/**
 * A setting action that cannot run as declared. Raised before anything executes.
 */
final class InvalidSettingActionException extends RuntimeException
{
    public static function missing(Setting $setting): self
    {
        return new self(sprintf('Setting %s has no action command.', $setting->name));
    }

    public static function unknownPlaceholder(Setting $setting, string $attribute): self
    {
        return new self(sprintf('Setting %s: unknown placeholder {%s}.', $setting->name, $attribute));
    }

    public static function encryptedValue(Setting $setting): self
    {
        return new self(sprintf('Setting %s is encrypted: its value cannot be passed to a command.', $setting->name));
    }

    public static function unregisteredCommand(Setting $setting, string $command): self
    {
        return new self(sprintf('Setting %s runs %s, which is not a registered command.', $setting->name, $command));
    }
}
