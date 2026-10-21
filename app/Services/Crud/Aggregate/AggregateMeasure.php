<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

/**
 * One figure an aggregate computes: a function over a column, or a bare `count` of the rows.
 */
final readonly class AggregateMeasure
{
    public function __construct(
        public string $fn,
        public ?string $field,
    ) {}

    /**
     * The key of the figure in a result: `count` for a count with no field, otherwise `<fn>_<field>`, so a
     * count over a field is `count_<field>`.
     */
    public function name(): string
    {
        if ($this->field === null) {
            return $this->fn;
        }

        return $this->fn . '_' . $this->field;
    }
}
