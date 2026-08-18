<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Contracts\ProvidesSyncableRelations;

final class CrudSyncRelParent extends Model implements ProvidesSyncableRelations
{
    public $timestamps = false;

    protected $table = 'crud_sync_rel_parent';

    protected $fillable = ['name'];

    /**
     * @return BelongsToMany<CrudSyncRelChild, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            CrudSyncRelChild::class,
            'crud_sync_rel_pivot',
            'parent_id',
            'child_id',
        );
    }

    /**
     * A non-many-to-many relation, to prove it is rejected even if whitelisted.
     *
     * @return HasMany<CrudSyncRelChild, $this>
     */
    public function offspring(): HasMany
    {
        return $this->hasMany(CrudSyncRelChild::class, 'parent_id');
    }

    /**
     * @return list<string>
     */
    public function syncableRelations(): array
    {
        return ['children', 'offspring'];
    }
}
