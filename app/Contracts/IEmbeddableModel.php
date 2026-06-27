<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for Eloquent models that use the Searchable trait for embeddings.
 */
interface IEmbeddableModel
{
    public function prepareDataToEmbed(): ?string;

    /**
     * @return MorphMany<\Modules\Core\Models\ModelEmbedding, $this>
     */
    public function embeddings(): MorphMany;
}
