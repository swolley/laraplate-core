<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Contracts\ISettingChangeConfirmation;

final class SettingChangeConfirmations
{
    /**
     * @var list<ISettingChangeConfirmation>
     */
    private array $confirmations = [];

    public function register(ISettingChangeConfirmation $confirmation): void
    {
        $this->confirmations[] = $confirmation;
    }

    public function for(string $settingName): ?ISettingChangeConfirmation
    {
        return array_find(
            $this->confirmations,
            static fn (ISettingChangeConfirmation $confirmation): bool => $confirmation->supports($settingName),
        );
    }
}
