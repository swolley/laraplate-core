<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\IMountsRelatedAttributes;

/**
 * A model that mounts the attributes of its `target` relation: a plain property it does not own is read
 * from the target.
 */
class MountingStubModel extends Model implements IMountsRelatedAttributes
{
    public $timestamps = false;

    protected $table = 'mounting_stub';

    protected $fillable = ['name', 'target_id'];

    /**
     * @return BelongsTo<DynamicOnlyStubModel, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(DynamicOnlyStubModel::class, 'target_id');
    }

    public function mountedRelation(): string
    {
        return 'target';
    }

    public function isMountedAttribute(string $key): bool
    {
        return ! in_array($key, ['id', 'name', 'target_id'], true) && ! method_exists($this, $key);
    }
}
