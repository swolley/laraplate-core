<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Stub whose facet label lives on a computed accessor (no backing column), used to
 * exercise the in-memory fallback of {@see \Modules\Core\Services\Crud\CrudService::facetCounts}.
 *
 * @property int $tier
 * @property-read string $tier_label
 */
class FacetAccessorStubModel extends Model
{
    protected $table = 'facet_accessor_stub';

    public $timestamps = false;

    protected $fillable = ['tier'];

    protected function casts(): array
    {
        return ['tier' => 'integer'];
    }

    /**
     * Computed label with no column of its own: it must be resolved by hydrating rows.
     */
    protected function tierLabel(): Attribute
    {
        return Attribute::get(fn (): string => 'Tier ' . $this->tier);
    }
}
