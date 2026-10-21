<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The customer side of the aggregate tests: the to-one relation an order is grouped and labelled by. Its name
 * is also kept per locale, for a translated label.
 *
 * @property int $id
 * @property string $name
 * @property string $country
 */
final class AggregateCustomerStubModel extends Model
{
    public $timestamps = false;

    protected $table = 'aggregate_customers';

    protected $fillable = ['name', 'country'];

    /**
     * @return HasMany<AggregateOrderStubModel, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(AggregateOrderStubModel::class, 'customer_id');
    }

    /**
     * @return HasMany<AggregateCustomerTranslationStubModel, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(AggregateCustomerTranslationStubModel::class, 'customer_id');
    }
}
