<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum ActionEnum: string
{
    case Select = 'select';
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case ForceDelete = 'forceDelete';
    case Approve = 'approve';
    // case Disapprove = 'disapprove';
    case Publish = 'publish';
    // case Unpublish = 'unpublish';
    case Impersonate = 'impersonate';
    case Lock = 'lock';
    // case Unlock = 'unlock';

    /**
     * returns if is a read action.
     */
    public static function isReadAction(string $action): bool
    {
        return $action === self::Select->value;
    }

    /**
     * returns if is a write action.
     */
    public static function isWriteAction(string $action): bool
    {
        return ! self::isReadAction($action);
    }
}
