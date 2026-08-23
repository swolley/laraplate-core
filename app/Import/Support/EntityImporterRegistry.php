<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Modules\Core\Import\Contracts\EntityImporterInterface;
use RuntimeException;

/**
 * The open registry of importable entities. Modules register their
 * {@see EntityImporterInterface}s here (typically in a service provider's boot),
 * and the framework resolves an importer by its key. Only registered entities are
 * importable — the framework never touches an arbitrary table.
 */
final class EntityImporterRegistry
{
    /**
     * @var array<string, EntityImporterInterface>
     */
    private array $importers = [];

    public function register(EntityImporterInterface $importer): void
    {
        $this->importers[$importer->key()] = $importer;
    }

    public function has(string $key): bool
    {
        return isset($this->importers[$key]);
    }

    public function get(string $key): EntityImporterInterface
    {
        return $this->importers[$key] ?? throw new RuntimeException("No import entity registered for key [{$key}].");
    }

    /**
     * All registered importers, keyed by their entity key.
     *
     * @return array<string, EntityImporterInterface>
     */
    public function all(): array
    {
        return $this->importers;
    }
}
