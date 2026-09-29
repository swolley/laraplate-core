<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use BackedEnum;
use Illuminate\Contracts\Console\Kernel;
use Modules\Core\Exceptions\InvalidSettingActionException;
use Modules\Core\Models\Setting;
use Stringable;

/**
 * Runs the Artisan command a seeded setting carries in `action_command`.
 *
 * `{attribute}` placeholders are replaced with the setting's attributes, each wrapped in
 * double quotes with `"` and `\` escaped. Symfony's StringInput reads that as one token,
 * so a value holding spaces or a leading `--` can never add arguments or options.
 */
final readonly class SettingActionRunner
{
    public function __construct(private Kernel $artisan) {}

    /**
     * @throws InvalidSettingActionException
     */
    public function commandLine(Setting $setting): string
    {
        $template = $setting->action_command;

        if (! is_string($template) || mb_trim($template) === '') {
            throw InvalidSettingActionException::missing($setting);
        }

        $attributes = $setting->getAttributes();

        return (string) preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static function (array $match) use ($setting, $attributes): string {
                $attribute = $match[1];

                if (! array_key_exists($attribute, $attributes)) {
                    throw InvalidSettingActionException::unknownPlaceholder($setting, $attribute);
                }

                if ($attribute === 'value' && $setting->encrypted) {
                    throw InvalidSettingActionException::encryptedValue($setting);
                }

                return self::quote(self::stringify($setting->getAttribute($attribute)));
            },
            $template,
        );
    }

    /**
     * @throws InvalidSettingActionException
     */
    public function run(Setting $setting): SettingActionResult
    {
        $line = $this->commandLine($setting);
        $command = (string) strtok($line, " \t");

        if (! array_key_exists($command, $this->artisan->all())) {
            throw InvalidSettingActionException::unregisteredCommand($setting, $command);
        }

        if ($setting->action_queued) {
            $this->artisan->queue($line);

            return SettingActionResult::queued($line);
        }

        $exit_code = $this->artisan->call($line);

        return SettingActionResult::ran($line, $exit_code, $this->artisan->output());
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof Stringable => (string) $value,
            default => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }

    private static function quote(string $value): string
    {
        return '"' . addcslashes($value, '"\\') . '"';
    }
}
