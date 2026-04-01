<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Filament;

use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Models\User;

final class HasFormHarness
{
    use HasForm;

    public static bool $loaded_permissions = false;

    public static function run(Schema $schema): void
    {
        self::configureForm($schema);
    }

    public static function loadUserPermissionsForTable(User $user): void
    {
        self::$loaded_permissions = $user->exists;
    }
}
