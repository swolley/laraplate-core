<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Import;

use Illuminate\Contracts\Container\Container;
use Modules\Core\Import\Contracts\BulkImporterInterface;
use Modules\Core\Import\Contracts\BulkImporterResolverInterface;

final readonly class FakeBulkImporterResolver implements BulkImporterResolverInterface
{
    public function __construct(
        private Container $container,
    ) {}

    public function resolve(string $importerClass, array $parameters): BulkImporterInterface
    {
        return $this->container->make($importerClass, $parameters);
    }

    public function contract(): string
    {
        return BulkImporterInterface::class;
    }
}
