<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Illuminate\Support\Facades\App;
use Mockery;

/**
 * Approvals never apply to console writes, and the whole suite runs in the console.
 *
 * A test that exercises capture has to pretend an HTTP request, which means swapping the
 * container for a partial mock whose runningInConsole() answers false. Lives here rather
 * than in a module's Pest.php because the approvals tests of Core, CMS and AI all need it,
 * and PSR-4 under Modules\Core\Tests\ reaches every suite through the merged autoload-dev.
 */
final class HttpContext
{
    /**
     * Make App::runningInConsole() answer false for the rest of the test.
     */
    public static function pretendHttpRequest(): void
    {
        $mock = Mockery::mock(App::getFacadeRoot())->makePartial();
        $mock->shouldReceive('runningInConsole')->andReturn(false);

        App::swap($mock);
    }
}
