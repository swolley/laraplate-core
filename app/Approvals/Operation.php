<?php

declare(strict_types=1);

namespace Modules\Core\Approvals;

/**
 * The write a Modification stands for.
 *
 * Replaces the boolean `is_update` the approval schema carried, which could only tell a
 * create from an update and had nothing to say about a deletion or a restore.
 */
enum Operation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case ForceDelete = 'force_delete';
    case Restore = 'restore';

    /**
     * Whether approving this operation removes the record, softly or for good.
     */
    public function isDeletion(): bool
    {
        return $this === self::Delete || $this === self::ForceDelete;
    }

    /**
     * Whether the modification carries a diff. Deletions and restores do not: they name a
     * record and an intent, and `modifications` stays empty.
     */
    public function carriesDiff(): bool
    {
        return $this === self::Create || $this === self::Update;
    }
}
