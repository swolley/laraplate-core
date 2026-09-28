<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\ISearchableContributor;
use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\Schema\FieldType;
use Modules\Core\Search\Schema\IndexType;

/**
 * Test contributor that adds two interpretive fields to {@see StubSearchableModel}.
 */
final class StubMediaContributor implements ISearchableContributor
{
    /**
     * @param  class-string<Model>  $target
     */
    public function __construct(private string $target = StubSearchableModel::class) {}

    public function contributesTo(): string
    {
        return $this->target;
    }

    public function searchableFields(Model $model): array
    {
        return ['idea' => 'freshness', 'intent' => 'inform'];
    }

    public function searchableMapping(): array
    {
        return [
            new FieldDefinition('idea', FieldType::Text, [IndexType::Searchable]),
            new FieldDefinition('intent', FieldType::Text, [IndexType::Searchable]),
        ];
    }
}
