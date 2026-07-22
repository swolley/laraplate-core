<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
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
     * @return list<string>
     */
    #[Override]
    public function getFillable(): array
    {
        $user_foreign_key = config('versionable.user_foreign_key', 'user_id');

        if (! is_string($user_foreign_key) || $user_foreign_key === '') {
            throw new InvalidArgumentException('The versionable user foreign key must be a non-empty string.');
        }

        return array_values(array_unique([...parent::getFillable(), $user_foreign_key]));
    }

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
