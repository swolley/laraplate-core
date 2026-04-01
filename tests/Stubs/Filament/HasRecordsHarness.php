<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Filament;

use Filament\Tables\Table;
use Modules\Core\Filament\Utils\HasRecords;

final class HasRecordsHarness extends HasRecordsParentHarness
{
    use HasRecords;

    public function callHeaderActions(): array
    {
        return $this->getHeaderActions();
    }

    public function callMakeTable(): Table
    {
        return $this->makeTable();
    }
}
