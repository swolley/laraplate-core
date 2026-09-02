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
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Support\PermissionName;
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
        // `insert` is the registered action name; `create` was never seeded, so the
        // check always failed for anyone but a super admin (Gate::before).
        $can_create = Auth::user()->can(PermissionName::forModel($model_instance, ActionEnum::Insert->value));

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
