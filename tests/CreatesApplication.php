<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

/**
 * Used by the parallel test runner to resolve the Laravel application in each worker process.
 * Delegates to LaravelTestCase so the same Testbench app (Core module, DB, auth) is used.
 */
trait CreatesApplication
{
    public function createApplication(): \Illuminate\Contracts\Foundation\Application
    {
        $testCase = new class() extends LaravelTestCase {};

        return $testCase->createApplication();
    }
}
