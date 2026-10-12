<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\IsPartOfParent;
use Override;

/**
 * A part whose declared parent is a part of the same class: its chain never reaches an owner.
 */
final class RelationCyclicPartStub extends Model implements IsPartOfParent
{
    protected $table = 'relauth_cyclic_parts';

    #[Override]
    public function parentRelation(): string
    {
        return 'container';
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(self::class, 'container_id');
    }
}
