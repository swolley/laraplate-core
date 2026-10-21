<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

/**
 * The figures of an aggregate request, in the shape of the contract: a series of groups, the total over every
 * row, the rest of the groups beyond a limit, the previous period and the facts about how they were computed.
 *
 * A figure is a number, or null when no row gave it (ruling R8). The minimum or the maximum of a date-time
 * column is the text the database holds, in the application time zone.
 */
final readonly class AggregateResult
{
    /**
     * @param  list<array{key: mixed, label: ?string, values: array<string, int|float|string|null>}>  $series
     * @param  array<string, int|float|string|null>  $total
     * @param  array<string, mixed>|null  $other
     * @param  array<string, mixed>|null  $compare
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $series,
        public array $total,
        public ?array $other,
        public ?array $compare,
        public array $meta,
    ) {}

    /**
     * The result as the route answers it: `other` and `compare` are present only when the request asked for them,
     * and `other` only when groups were left out of the series.
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        $data = [
            'series' => $this->series,
            'total' => $this->total,
        ];

        if ($this->other !== null) {
            $data['other'] = $this->other;
        }

        if ($this->compare !== null) {
            $data['compare'] = $this->compare;
        }

        return ['data' => $data, 'meta' => $this->meta];
    }
}
