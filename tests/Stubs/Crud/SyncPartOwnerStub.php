<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Contracts\ProvidesSyncableRelations;
use Override;

/**
 * A model that syncs the parts it owns: a part synced from its own parent needs no permission of its own.
 */
final class SyncPartOwnerStub extends Model implements ProvidesSyncableRelations
{
    public $timestamps = false;

    protected $table = 'crud_sync_part_owner';

    protected $guarded = [];

    /**
     * @return BelongsToMany<SyncPartStub, $this>
     */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(SyncPartStub::class, 'crud_sync_part_pivot', 'owner_id', 'part_id');
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function syncableRelations(): array
    {
        return ['parts'];
    }
}
