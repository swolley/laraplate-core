<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use InvalidArgumentException;

/**
 * A condition on a record related to the filtered one, e.g. "the media whose owner is a published content".
 *
 * The nested {@see FiltersGroup} is evaluated on the related record with the same operators and dynamic
 * values (`@now`, `@today`, `@user.<attribute>`) as any other filter. A morph relation lists the types it
 * accepts in `morph_types`; a media row owned by a type outside the list never matches.
 *
 * Only ACLs build it: the filters a caller sends in a request never produce one.
 */
final readonly class RelationFilter
{
    /**
     * @param  list<string>|null  $morph_types  The class names or morph aliases a MorphTo relation accepts, null for any other relation
     */
    public function __construct(
        public string $relation,
        public FiltersGroup $filters,
        public ?array $morph_types = null,
    ) {
        if ($morph_types === []) {
            throw new InvalidArgumentException('A relation filter on a morph relation must accept at least one morph type.');
        }
    }

    /**
     * @return array{relation: string, morph_types?: list<string>, filters: array{filters: list<array<string, mixed>>, operator: string}}
     */
    public function toArray(): array
    {
        $data = ['relation' => $this->relation];

        if ($this->morph_types !== null) {
            $data['morph_types'] = $this->morph_types;
        }

        $data['filters'] = $this->filters->toArray();

        return $data;
    }
}
