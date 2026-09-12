<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Traits\Searchable;

/**
 * Throwaway searchable model for asserting that free-text keyword search still
 * matches mono-language models (e.g. Ticket, Location) whose `title` is a flat
 * text field rather than a per-locale object: `TextMatchOptionsResolver` must
 * keep resolving `fields` to the literal `title`/`*`, not a `title.*` locale
 * wildcard that would never match a flat field.
 */
class FlatTitleStubModel extends Model
{
    use Searchable;

    public const string INDEX = 'core_test_flat_title';

    public function searchableAs(): string
    {
        return self::INDEX;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSearchMapping(): array
    {
        return [
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text', 'analyzer' => 'standard'],
                    'description' => ['type' => 'text', 'analyzer' => 'standard'],
                ],
            ],
        ];
    }
}
