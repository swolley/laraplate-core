<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The measured table of the aggregate tests: a decimal amount with its currency, an integer, a date, a
 * boolean and a to-one relation to {@see AggregateCustomerStubModel}. `total_label` is a computed accessor
 * with no column behind it.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string $status
 * @property string|null $total
 * @property int $quantity
 * @property string|null $currency
 * @property Carbon $ordered_at
 * @property bool $is_paid
 * @property-read string $total_label
 */
final class AggregateOrderStubModel extends Model
{
    public $timestamps = false;

    protected $table = 'aggregate_orders';

    protected $fillable = ['customer_id', 'status', 'total', 'quantity', 'currency', 'ordered_at', 'is_paid'];

    /**
     * @return BelongsTo<AggregateCustomerStubModel, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(AggregateCustomerStubModel::class, 'customer_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:4',
            'quantity' => 'integer',
            'ordered_at' => 'datetime',
            'is_paid' => 'boolean',
        ];
    }

    protected function totalLabel(): Attribute
    {
        return Attribute::get(fn (): string => 'Total ' . $this->total);
    }
}
