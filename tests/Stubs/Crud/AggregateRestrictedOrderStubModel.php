<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IRestrictsReadableColumns;

/**
 * The orders of the aggregate tests read by a caller who may not read their amount: every column of
 * `aggregate_orders` is readable but `total`, as the media projection withholds columns from the `api` guard.
 *
 * @property int $id
 * @property string|null $total
 */
final class AggregateRestrictedOrderStubModel extends Model implements IRestrictsReadableColumns
{
    public $timestamps = false;

    protected $table = 'aggregate_orders';

    /**
     * @return list<string>
     */
    public function readableColumnsForCurrentCaller(): array
    {
        return ['id', 'customer_id', 'status', 'quantity', 'currency', 'ordered_at', 'is_paid'];
    }
}
