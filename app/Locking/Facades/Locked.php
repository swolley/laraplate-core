<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Facades;

use Override;
use Illuminate\Support\Facades\Facade;

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
