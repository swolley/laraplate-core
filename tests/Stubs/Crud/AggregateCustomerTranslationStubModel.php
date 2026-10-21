<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;

/**
 * The name of an {@see AggregateCustomerStubModel} in one locale: the translation row a declared label source
 * reads a translated group label from.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $locale
 * @property string $name
 */
final class AggregateCustomerTranslationStubModel extends Model
{
    public $timestamps = false;

    protected $table = 'aggregate_customer_translations';

    protected $fillable = ['customer_id', 'locale', 'name'];
}
