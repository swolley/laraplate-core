<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Filament;

use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

class HasRecordsParentHarness
{
    public function __construct(protected Table $table) {}

    /**
     * @return Collection<int, int>|Paginator|CursorPaginator
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        return collect([1, 2, 3]);
    }

    protected static function getResource(): string
    {
        return HasRecordsResourceHarness::class;
    }

    protected function makeTable(): Table
    {
        return $this->table;
    }
}
