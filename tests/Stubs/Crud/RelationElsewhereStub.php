<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A model that reaches the parts of {@see RelationOwnerStub} without being their parent.
 */
final class RelationElsewhereStub extends Model
{
    protected $table = 'relauth_elsewhere';

    /**
     * @return HasMany<RelationPartStub, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(RelationPartStub::class, 'elsewhere_id');
    }
}
