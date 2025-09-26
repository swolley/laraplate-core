<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Modules\Core\Search\Schema\SchemaDefinition;

interface ISchemaTranslator
{
    /**
     * Translate a generic schema definition to engine-specific format.
     */
    public function translate(SchemaDefinition $schema): array;

    /**
     * Get the engine name this translator supports.
     */
    public function getEngineName(): string;
}
