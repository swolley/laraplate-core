<?php

declare(strict_types=1);

namespace Modules\Core\Locking;

use DateTimeInterface;

/**
 * What a caller is asking for when it locks a record.
 *
 * Two axes, matching the two columns. `freeze` says whether the resulting lock carries an owner:
 * an ownerless lock blocks everyone, an owned one is a lease only its holder may edit under.
 * `until` says when it lapses, with null meaning never.
 *
 * `until_is_explicit` separates the two ways a deadline arrives. The edit form refreshes its lease
 * implicitly and gets the configured TTL, which may only ever push the deadline further out: a user
 * who deliberately held a record until Thursday must not lose that by reopening the form. A caller
 * that names a deadline is making an assignment, and its own lock is its to shorten.
 */
final readonly class LockIntent
{
    public function __construct(
        public bool $freeze,
        public ?int $user_id,
        public ?DateTimeInterface $until,
        public bool $until_is_explicit,
        public string $permission_name,
    ) {}
}
