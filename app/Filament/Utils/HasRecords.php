<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
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
    /**
     * @var list<\Filament\Tables\Grouping\Group|string>
     */
    private array $groups = [];

    /**
     * Measure fetch time and share it for the pagination overview (e.g. "Mostrati da 1 a 10 di 15,021 risultati in 0.12 s").
     *
     * @return Collection<int, Model>|Paginator<int, Model>|CursorPaginator<int, Model>
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
        $user = Auth::user();

        // The page is behind the panel's auth middleware, so a null user here means
        // the request never should have reached it: offering no create action is the
        // safe reading, and newInstanceWithoutConstructor() gives back a bare object.
        if ($user === null || ! $model_instance instanceof Model) {
            return [];
        }

        // `insert` is the registered action name; `create` was never seeded, so the
        // check always failed for anyone but a super admin (Gate::before).
        $can_create = $user->can(PermissionName::forModel($model_instance, ActionEnum::Insert->value));

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
