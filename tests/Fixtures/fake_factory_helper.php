<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

/**
 * Forwarder so that factory classes calling fake() resolve to the global helper in standalone tests.
 */
function fake(?string $locale = null): \Faker\Generator
{
    return \fake($locale);
}
