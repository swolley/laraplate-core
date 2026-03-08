<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Override;
use ReflectionClass;

trait HasRecords
{
    private array $groups = [];

    /**
     * Measure fetch time and share it for the pagination overview (e.g. "Mostrati da 1 a 10 di 15,021 risultati in 0.12 s").
     *
     * @return Collection<int, mixed>|Paginator|CursorPaginator
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $start = microtime(true);
        $records = parent::getTableRecords();
        $ms = (int) round((microtime(true) - $start) * 1000);
        $seconds = $ms >= 1000 ? round($ms / 1000, 2) : round($ms / 1000, 3);
        View::share('tableFetchDurationSeconds', $seconds);

        return $records;
    }

    protected function getHeaderActions(): array
    {
        $model = self::getResource()::getModel();
        $model_instance = new ReflectionClass($model)->newInstanceWithoutConstructor();
        $model_table = $model_instance->getTable();
        $model_connection = $model_instance->getConnectionName() ?? 'default';
        $permissions_prefix = sprintf('%s.%s', $model_connection, $model_table);

        $can_create = Auth::user()->can($permissions_prefix . '.create');

        return $can_create ? [
            CreateAction::make()->icon(Heroicon::OutlinedPlus),
        ] : [];
    }

    #[Override]
    protected function makeTable(): Table
    {
        $table = parent::makeTable();

        if (count($this->groups) > 0) {
            $table->groups($this->groups);
        }

        return $table;
    }
}
