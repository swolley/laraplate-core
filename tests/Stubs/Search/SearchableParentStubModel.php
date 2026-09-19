<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Search\Traits\Searchable;

/**
 * Searchable model that declares a relation to eager-load for indexing via
 * toSearchableWith(), so makeSearchableUsing() can be exercised.
 */
final class SearchableParentStubModel extends Model implements ISearchableModel
{
    use Searchable;

    public $timestamps = false;

    protected $table = 'searchable_parent_stubs';

    protected $guarded = [];

    /**
     * @return HasMany<SearchableChildStubModel, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(SearchableChildStubModel::class, 'parent_id');
    }

    /**
     * @return list<string>
     */
    public function toSearchableWith(): array
    {
        return ['children'];
    }
}
