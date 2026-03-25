<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

/**
 * Stub used to cover the path where userUpserter is null.
 * user_class() is set to this class; query() returns the mock builder that updateOrCreate().
 */
class HandleSocialLoginActionTestUserDouble
{
    public static $queryBuilder;

    public static function query(): mixed
    {
        return self::$queryBuilder;
    }
}
