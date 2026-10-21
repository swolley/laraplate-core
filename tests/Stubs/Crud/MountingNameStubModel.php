<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\IMountsRelatedAttributes;

/**
 * A model that owns only its keys and presents the `name` of its `target` as its own: the case of a record
 * whose identity lives on a related record.
 */
class MountingNameStubModel extends Model implements IMountsRelatedAttributes
{
    public $timestamps = false;

    protected $table = 'mounting_name_stub';

    protected $fillable = ['target_id'];

    /**
     * @return BelongsTo<PlainTargetStubModel, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(PlainTargetStubModel::class, 'target_id');
    }

    public function mountedRelation(): string
    {
        return 'target';
    }

    public function isMountedAttribute(string $key): bool
    {
        return $key === 'name';
    }
}
