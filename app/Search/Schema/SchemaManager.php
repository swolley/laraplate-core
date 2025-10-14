<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

use InvalidArgumentException;
use Modules\Core\Search\Contracts\ISchemaTranslator;
use Modules\Core\Search\Translators\DatabaseTranslator;
use Modules\Core\Search\Translators\ElasticsearchTranslator;
use Modules\Core\Search\Translators\TypesenseTranslator;

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
        throw_unless(isset($this->translators[$engine]), InvalidArgumentException::class, "No translator found for engine: {$engine}");

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
        $this->registerTranslator(new DatabaseTranslator());
    }
}
