<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures\ImportPlugin;

use Modules\Core\Import\Contracts\BulkImporterInterface;

final class ConcreteFixtureImporter implements BulkImporterInterface
{
    public function import(): int
    {
        return 1;
    }
}
