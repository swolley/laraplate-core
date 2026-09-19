<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Child row for {@see SearchableParentStubModel}, used to exercise the
 * makeSearchableUsing eager-load without a search server.
 */
final class SearchableChildStubModel extends Model
{
    public $timestamps = false;

    protected $table = 'searchable_child_stubs';

    protected $guarded = [];

    /**
     * @return BelongsTo<SearchableParentStubModel, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SearchableParentStubModel::class, 'parent_id');
    }
}
