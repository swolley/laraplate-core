<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Data;

use InvalidArgumentException;

abstract readonly class GraphToolInput
{
    private const int MAX_DEPTH = 2;

    private const int MAX_RELATION_LIMIT = 10;

    /**
     * @param  list<string>  $relations
     */
    public function __construct(
        public string $module,
        public string $entity,
        public array $relations,
        public int $depth,
        public int $relationLimit,
    ) {
        self::assertIdentifier($this->module, 'module');
        self::assertIdentifier($this->entity, 'entity');

        if ($this->depth < 1 || $this->depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Graph tool depth is outside the allowed range.');
        }

        if ($this->relationLimit < 1 || $this->relationLimit > self::MAX_RELATION_LIMIT) {
            throw new InvalidArgumentException('Graph tool relation limit is outside the allowed range.');
        }

        foreach ($this->relations as $relation) {
            if (! is_string($relation)
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $relation) !== 1
                || substr_count($relation, '.') + 1 > $this->depth) {
                throw new InvalidArgumentException('Graph tool relation is invalid or exceeds the requested depth.');
            }
        }
    }

    private static function assertIdentifier(string $value, string $name): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("Graph tool {$name} is invalid.");
        }
    }
}
