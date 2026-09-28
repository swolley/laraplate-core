<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasModerationMeta;
use Override;

/**
 * One vote in favour of a captured write.
 *
 * Derived from cloudcake/laravel-approval (MIT), see LICENSES/laravel-approval.md.
 */
final class Approval extends Model
{
    use HasModerationMeta;

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::Approvals->value;

    /**
     * @var list<string>
     */
    #[Override]
    protected $guarded = ['id'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function approver(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Modification, $this>
     */
    public function modification(): BelongsTo
    {
        return $this->belongsTo(Modification::class);
    }
}
