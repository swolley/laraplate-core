<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

/**
 * Engine stand-in that only reports a driver name, which is all the ensemble reads from it.
 */
final class FusionFixtureEngineName
{
    public function getName(): string
    {
        return 'fusion-fixture';
    }
}
