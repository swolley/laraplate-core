<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The parent of {@see RelationPartStub}: a part loaded from it inherits its visibility.
 */
final class RelationOwnerStub extends Model
{
    protected $table = 'relauth_owners';

    /**
     * @return HasMany<RelationPartStub, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(RelationPartStub::class, 'owner_id');
    }
}
