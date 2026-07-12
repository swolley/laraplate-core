<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

readonly class GraphRelation
{
    /**
     * @param  class-string<Model>  $relatedClass
     */
    public function __construct(
        public string $name,
        public Relation $relation,
        public string $relatedClass,
        public bool $isMultiple,
    ) {}
}
