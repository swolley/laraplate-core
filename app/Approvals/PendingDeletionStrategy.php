<?php

declare(strict_types=1);

namespace Modules\Core\Approvals;

/**
 * What a record whose deletion waits for approval looks like until the decision.
 */
enum PendingDeletionStrategy
{
    /**
     * The record stays visible and refuses every change, except the attributes its model declares writable.
     */
    case Block;

    /**
     * The record disappears for everyone who cannot decide on the deletion.
     */
    case Hide;

    /**
     * Name of the global scope that filters Hide records out.
     */
    public const string HIDE_SCOPE = 'hide_pending_deletion';
}
