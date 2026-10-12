<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\IsPartOfParent;
use Override;

/**
 * A record that only exists inside a {@see RelationPartStub}, itself a part: its owner is two steps up.
 */
final class RelationSubPartStub extends Model implements IsPartOfParent
{
    protected $table = 'relauth_sub_parts';

    #[Override]
    public function parentRelation(): string
    {
        return 'part';
    }

    /**
     * @return BelongsTo<RelationPartStub, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(RelationPartStub::class, 'part_id');
    }
}
