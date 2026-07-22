<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Enums\VersionSetKind;
use Override;

/**
 * @property int $id
 * @property string $uuid
 * @property VersionSetKind $kind
 * @property int|null $reverted_from_set_id
 *
 * @mixin \Eloquent
 */
final class VersionSet extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::VersionSets->value;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'uuid',
        'root_type',
        'root_id',
        'root_connection_ref',
        'root_table_ref',
        'kind',
        'reason',
        'reverted_from_set_id',
    ];

    /**
     * @return HasMany<Version>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Version::class)->orderBy('sequence');
    }

    /**
     * @return BelongsTo<VersionSet, VersionSet>
     */
    public function revertedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverted_from_set_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => VersionSetKind::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
