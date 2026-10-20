<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * What happened to the row, in the names of the Eloquent lifecycle events (D20): a root goes to the bin
 * (`trashed`) and leaves it (`restored`); `deleted` is a row physically gone, the tombstone of a hard delete
 * or a detached membership.
 */
enum VersionChangeType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Trashed = 'trashed';
    case Restored = 'restored';
    case Deleted = 'deleted';
}
