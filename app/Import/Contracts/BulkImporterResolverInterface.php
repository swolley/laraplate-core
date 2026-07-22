<?php

declare(strict_types=1);

namespace Modules\Core\Import\Contracts;

interface BulkImporterResolverInterface
{
    /**
     * @param  class-string  $importerClass
     * @param  array<string, mixed>  $parameters
     */
    public function resolve(string $importerClass, array $parameters): BulkImporterInterface;

    /**
     * @return class-string<BulkImporterInterface>
     */
    public function contract(): string;
}
