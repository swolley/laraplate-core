<?php

namespace Modules\Core\Filament\Resources\ACLS\Tables;

use \Override;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\BaseTable;
use Modules\Core\Models\ACL;

final class ACLsTable extends BaseTable
{
    #[Override]
    protected function getModel(): string
    {
        return ACL::class;
    }

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: function (Collection $default_columns) {
                $default_columns->unshift(...[
                    TextColumn::make('permission.name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('description')
                        ->searchable()
                        ->toggleable(),
                ]);
            },
        );
    }
}
