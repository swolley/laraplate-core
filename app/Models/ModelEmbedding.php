<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
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
     * The model that belongs to the embedding.
     *
     * @return MorphTo<Model>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'embedding' => 'json',
        ];
    }
}
