<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Filament;

use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;

final class HasFormHarness
{
    use HasForm;

    public static function run(Schema $schema): Schema
    {
        return self::configureForm($schema);
    }
}
