<?php

declare(strict_types=1);

namespace Modules\Core\DTOs;

/**
 * What the confirmation modal shows before a setting change is saved.
 */
final readonly class SettingChangeWarning
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $title,
        public array $lines = [],
    ) {}
}
