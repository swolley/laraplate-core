<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\ImportPluginFixtures\src;

use Modules\Core\Import\Contracts\BulkImporterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConcreteFixtureImporter implements BulkImporterInterface
{
    public function import(?OutputInterface $output = null): int
    {
        return 1;
    }
}

