<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Laravel\Passkeys\Passkey as BasePasskey;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * The package model on the Core-prefixed table, so every Core table follows one naming rule.
 */
final class Passkey extends BasePasskey
{
    #[Override]
    protected $table = CoreTables::Passkeys->value;
}
