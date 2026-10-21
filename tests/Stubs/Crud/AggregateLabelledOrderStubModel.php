<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ProvidesFacetLabelSources;
use Modules\Core\Services\Crud\DTOs\FacetLabelSource;
use Override;

/**
 * The orders of the aggregate tests labelled through sources the model declares instead of a relation, each
 * keyed by `customer_id`: a translated customer name, and two sources whose translation does not resolve (a
 * translation relation the customer lacks, and no translation column), which the label resolver reads as plain
 * customer columns.
 *
 * @property int $id
 * @property int|null $customer_id
 */
final class AggregateLabelledOrderStubModel extends Model implements ProvidesFacetLabelSources
{
    public $timestamps = false;

    protected $table = 'aggregate_orders';

    /**
     * @return array<string, FacetLabelSource>
     */
    #[Override]
    public function facetLabelSources(): array
    {
        return [
            'translated_customer' => new FacetLabelSource(
                relatedClass: AggregateCustomerStubModel::class,
                foreignKey: 'customer_id',
                translationRelation: 'translations',
                translationColumn: 'name',
            ),
            'unresolved_translation' => new FacetLabelSource(
                relatedClass: AggregateCustomerStubModel::class,
                foreignKey: 'customer_id',
                translationRelation: 'localizations',
                translationColumn: 'title',
            ),
            'untranslated_column' => new FacetLabelSource(
                relatedClass: AggregateCustomerStubModel::class,
                foreignKey: 'customer_id',
                translationRelation: 'translations',
            ),
        ];
    }
}
