<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $url
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
        'url',
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

    protected static function newFactory(): RecordOriginFactory
    {
        return RecordOriginFactory::new();
    }
}
