<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Casts;

use Modules\Core\Casts\ActionEnum;

enum GridAction: string
{
    case Select = ActionEnum::Select->value;
    case Insert = ActionEnum::Insert->value;
    case Update = ActionEnum::Update->value;
    case Delete = ActionEnum::Delete->value;
    // case Restore = 'restore';
    case ForceDelete = ActionEnum::ForceDelete->value;
    case Approve = ActionEnum::Approve->value;
    // case Disapprove = 'disapprove';
    // case Impersonate = 'impersonate';
    case Lock = ActionEnum::Lock->value;
    // case Unlock = 'unlock';
    case Funnels = 'funnels';
    case Options = 'options';
    case Data = 'data';
    case Export = 'export';
    case Check = 'check';
    case GetAll = 'get_all';

    public static function values(): array
    {
        return array_map(fn (GridAction $case) => $case->value, self::cases());
    }

    /**
     * returns if is a read action.
     */
    public static function isReadAction(GridAction|string $action): bool
    {
        if ($action instanceof GridAction) {
            $action = $action->value;
        }

        return match ($action) {
            self::Insert->value, self::Update->value, self::Delete->value, self::ForceDelete->value, self::Approve->value, self::Lock->value => false,
            default => true,
        };
    }

    /**
     * returns if is a write action.
     */
    public static function isWriteAction(GridAction|string $action): bool
    {
        return ! self::isReadAction($action);
    }
}
