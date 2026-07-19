<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Data;

use InvalidArgumentException;

final readonly class GraphExpandToolInput extends GraphToolInput
{
    /**
     * @param  list<string>  $relations
     */
    public function __construct(
        string $module,
        string $entity,
        public int|string $recordKey,
        array $relations = [],
        int $depth = 1,
        public int $limit = 25,
        int $relationLimit = 10,
    ) {
        parent::__construct($module, $entity, $relations, $depth, $relationLimit);

        if ($this->relations === []
            || (is_string($this->recordKey) && trim($this->recordKey) === '')
            || $this->limit < 1
            || $this->limit > 25) {
            throw new InvalidArgumentException('Graph tool expand input is outside the allowed range.');
        }
    }
}
