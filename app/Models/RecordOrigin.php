<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Database\Factories\RecordOriginFactory;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * Tracks the provenance of any record: which external source it originates from
 * (imported entities) or a manual attribution. Also acts as the import identity
 * registry, mapping an external source id to a local record.
 *
 * @property int $id
 * @property string $referable_type
 * @property int $referable_id
 * @property string $source_key
 * @property string|null $source_label
 * @property string|null $external_id
 * @property string|null $fingerprint
 * @property string|null $url
 * @property CarbonImmutable|null $source_updated_at
 *
 * @mixin \Eloquent
 * @mixin IdeHelperRecordOrigin
 */
final class RecordOrigin extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'referable_type',
        'referable_id',
        'source_key',
        'source_label',
        'external_id',
        'fingerprint',
        'url',
        'source_updated_at',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::RecordOrigins->value;

    /**
     * The record this origin belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function referable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): RecordOriginFactory
    {
        return RecordOriginFactory::new();
    }

    /**
     * Scope to the origin(s) of a given model instance, leveraging the composite morphs() index.
     *
     * @param  Builder<RecordOrigin>  $query
     * @return Builder<RecordOrigin>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function forReferable(Builder $query, Model $model): Builder
    {
        return $query
            ->where('referable_type', $model->getMorphClass())
            ->where('referable_id', $model->getKey());
    }

    /**
     * Stored as UTC and exposed in the current application timezone, so the instant
     * survives timezone changes between import and read.
     */
    protected function sourceUpdatedAt(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value): ?CarbonImmutable => $value === null
                ? null
                : CarbonImmutable::parse($value, 'UTC')->setTimezone(config('app.timezone')),
            set: static fn (DateTimeInterface|string|null $value): ?string => match (true) {
                $value === null => null,
                $value instanceof DateTimeInterface => CarbonImmutable::instance($value)->utc()->format('Y-m-d H:i:s'),
                default => CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s'),
            },
        );
    }
}
