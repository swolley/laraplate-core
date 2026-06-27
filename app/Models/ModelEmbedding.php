<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * @property int|null $id
 * @property array<int, float>|null $embedding
 * @property int|null $model_id
 * @property string|null $model_type
 * @mixin \Eloquent
 * @mixin IdeHelperModelEmbedding
 */
final class ModelEmbedding extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'embedding',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::ModelEmbeddings->value;

    /**
     * The model that belongs to the embedding.
     *
     * @return MorphTo<Model>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter embeddings by a specific model instance.
     * Filters on both model_type and model_id to leverage the composite morphs() index.
     *
     * @param  Builder<ModelEmbedding>  $query
     * @return Builder<ModelEmbedding>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function forModel(Builder $query, Model $model): Builder
    {
        return $query
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey());
    }

    protected function casts(): array
    {
        return [
            'embedding' => 'json',
        ];
    }
}
