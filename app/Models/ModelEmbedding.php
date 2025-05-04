<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Override;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// use Modules\Core\Database\Factories\ModelEmbeddingFactory;

/**
 * @mixin IdeHelperModelEmbedding
 */
final class ModelEmbedding extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'embedding',
    ];

    /**
     * The model that belongs to the embedding.
     *
     * @return MorphTo<Model>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'embedding' => 'json',
        ];
    }
}
