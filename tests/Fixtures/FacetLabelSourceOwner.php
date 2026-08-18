<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ProvidesFacetLabelSources;
use Modules\Core\Models\License;
use Modules\Core\Services\Crud\DTOs\FacetLabelSource;
use Override;

/**
 * Rides the `users` table but deliberately declares NO `license()` BelongsTo, so
 * the facet engine can only reach the license label through the declared source —
 * the same shape as a model exposing its foreign key via an accessor.
 */
final class FacetLabelSourceOwner extends Model implements ProvidesFacetLabelSources
{
    protected $table = 'users';

    protected $guarded = [];

    /**
     * @return array<string, FacetLabelSource>
     */
    #[Override]
    public function facetLabelSources(): array
    {
        return [
            'lic' => new FacetLabelSource(relatedClass: License::class, foreignKey: 'license_id'),
        ];
    }
}
