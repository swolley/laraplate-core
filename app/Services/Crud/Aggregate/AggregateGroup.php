<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Modules\Core\Enums\TimeBucket;

/**
 * The one dimension an aggregate is split by: the value of a column, or the calendar bucket of a date column.
 * `label` names a to-one relation column that labels the groups of a foreign-key column.
 */
final readonly class AggregateGroup
{
    public function __construct(
        public string $field,
        public ?TimeBucket $bucket = null,
        public ?string $label = null,
    ) {}
}
