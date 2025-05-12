<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @see \Sfolador\Locked\Locked
 */
final class Locked extends Facade
{
    #[Override]
    protected static function getFacadeAccessor()
    {
        return \Modules\Core\Locking\Locked::class;
    }
}
