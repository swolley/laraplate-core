<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\IsPartOfParent;
use Override;

/**
 * A record that only exists inside a {@see SyncPartOwnerStub}, attached to it through a pivot.
 */
final class SyncPartStub extends Model implements IsPartOfParent
{
    public $timestamps = false;

    protected $table = 'crud_sync_part';

    protected $guarded = [];

    #[Override]
    public function parentRelation(): string
    {
        return 'owner';
    }

    /**
     * @return BelongsTo<SyncPartOwnerStub, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(SyncPartOwnerStub::class, 'owner_id');
    }
}
