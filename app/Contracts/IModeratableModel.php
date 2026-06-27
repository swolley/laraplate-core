<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Contract for Eloquent models that use the HasApprovals trait with AI moderation settings.
 */
interface IModeratableModel
{
    public function aiModerationEnabledBySettings(): bool;
}
