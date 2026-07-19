<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Data;

use InvalidArgumentException;

final readonly class GraphSearchToolInput extends GraphToolInput
{
    /**
     * @param  list<string>  $relations
     */
    public function __construct(
        string $module,
        string $entity,
        public string $query,
        array $relations = [],
        int $depth = 1,
        public int $limit = 10,
        int $relationLimit = 10,
    ) {
        parent::__construct($module, $entity, $relations, $depth, $relationLimit);

        if (trim($this->query) === '' || mb_strlen($this->query) > 500) {
            throw new InvalidArgumentException('Graph tool search query is invalid.');
        }

        if ($this->limit < 1 || $this->limit > 10) {
            throw new InvalidArgumentException('Graph tool search limit is outside the allowed range.');
        }
    }
}
