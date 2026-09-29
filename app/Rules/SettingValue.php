<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Override;

/**
 * A setting's value must match its type and, when it has them, its choices.
 *
 * The type and the choices come from the data being validated. A partial update through the
 * API may carry neither, and its rules are built on an empty model whose type is the default,
 * so the stored setting named by `id` fills in what the data lacks.
 *
 * The value already saved is exempt from the choices: a refresh command may rewrite the
 * choices so that they no longer offer it, and the setting keeps it, flagged by
 * {@see Setting::isValueOutsideChoices()}, until somebody picks another one.
 */
final class SettingValue implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * The setting named by `id`, looked up at most once per validation.
     */
    private ?Setting $stored = null;

    private bool $storedLookedUp = false;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    public function setData(array $data): static
    {
        $this->data = $data;
        $this->stored = null;
        $this->storedLookedUp = false;

        return $this;
    }

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        [$type, $choices] = $this->typeAndChoices();

        if (! $type instanceof SettingTypeEnum) {
            return;
        }

        if ($choices !== [] && $this->isStoredValue($value)) {
            $choices = [];
        }

        $validator = Validator::make(['value' => $value], self::rulesFor($type, $choices, $value));

        foreach ($validator->errors()->all() as $message) {
            $fail($message);
        }
    }

    /**
     * @param  list<string>  $choices
     * @return array<string, list<mixed>>
     */
    private static function rulesFor(SettingTypeEnum $type, array $choices, mixed $value): array
    {
        return match ($type) {
            SettingTypeEnum::Boolean => ['value' => ['boolean']],
            SettingTypeEnum::Integer => ['value' => ['integer']],
            SettingTypeEnum::Float => ['value' => ['numeric']],
            SettingTypeEnum::Date => ['value' => ['date']],
            SettingTypeEnum::String => ['value' => $choices === [] ? ['string'] : ['string', Rule::in($choices)]],
            SettingTypeEnum::Json => match (true) {
                $choices === [] => [],
                is_array($value) => ['value' => ['array'], 'value.*' => [Rule::in($choices)]],
                default => ['value' => [Rule::in($choices)]],
            },
        };
    }

    /**
     * @return array{0: ?SettingTypeEnum, 1: list<string>}
     */
    private function typeAndChoices(): array
    {
        $record = null;

        if (! array_key_exists('type', $this->data) || ! array_key_exists('choices', $this->data)) {
            $record = $this->storedSetting();
        }

        $type = array_key_exists('type', $this->data) ? $this->data['type'] : $record?->type;
        $type = $type instanceof SettingTypeEnum ? $type : SettingTypeEnum::tryFrom((string) $type);

        $choices = array_key_exists('choices', $this->data) ? $this->data['choices'] : $record?->choices;
        $choices = is_array($choices)
            ? array_values(array_map(strval(...), array_filter($choices, is_scalar(...))))
            : [];

        return [$type, $choices];
    }

    /**
     * Whether the value is the one the setting already holds, compared as the form and the API
     * send it: a scalar by its string form, a list by its items.
     */
    private function isStoredValue(mixed $value): bool
    {
        $stored = $this->storedSetting()?->value;

        if (is_scalar($stored) && is_scalar($value)) {
            return (string) $stored === (string) $value;
        }

        return is_array($stored) && is_array($value) && $stored === $value;
    }

    private function storedSetting(): ?Setting
    {
        if (! $this->storedLookedUp) {
            $key = $this->data['id'] ?? null;
            $this->stored = is_int($key) || is_string($key) ? Setting::query()->find($key) : null;
            $this->storedLookedUp = true;
        }

        return $this->stored;
    }
}
