<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

/**
 * A model whose rows live long enough that the configured default window would
 * never report one, so it names its own. Exercises the per-model override in
 * {@see \Modules\Core\Models\Concerns\HasValidity::expiringWithinHours()}.
 */
class LongLivedValidityStubModel extends ValidityStubModel
{
    protected static int $expiring_within_hours = 720;
}
