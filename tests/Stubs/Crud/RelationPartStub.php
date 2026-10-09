<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\IsPartOfParent;
use Override;

/**
 * A record that only exists inside a {@see RelationOwnerStub}.
 */
final class RelationPartStub extends Model implements IsPartOfParent
{
    protected $table = 'relauth_parts';

    #[Override]
    public function parentRelation(): string
    {
        return 'owner';
    }

    /**
     * @return BelongsTo<RelationOwnerStub, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(RelationOwnerStub::class, 'owner_id');
    }
}
