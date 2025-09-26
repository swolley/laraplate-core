<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

use InvalidArgumentException;
use Modules\Core\Search\Contracts\ISchemaTranslator;
use Modules\Core\Search\Schema\Translators\ElasticsearchTranslator;
use Modules\Core\Search\Schema\Translators\TypesenseTranslator;

class SchemaManager
{
    /**
     * @var ISchemaTranslator[]
     */
    private array $translators = [];

    public function __construct()
    {
        $this->registerDefaultTranslators();
    }

    public function registerTranslator(ISchemaTranslator $translator): void
    {
        $this->translators[$translator->getEngineName()] = $translator;
    }

    public function translateForEngine(SchemaDefinition $schema, string $engine): array
    {
        if (! isset($this->translators[$engine])) {
            throw new InvalidArgumentException("No translator found for engine: {$engine}");
        }

        return $this->translators[$engine]->translate($schema);
    }

    public function getSupportedEngines(): array
    {
        return array_keys($this->translators);
    }

    private function registerDefaultTranslators(): void
    {
        $this->registerTranslator(new ElasticsearchTranslator());
        $this->registerTranslator(new TypesenseTranslator());
    }
}
