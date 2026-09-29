<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Media;
use Modules\Core\Search\Contracts\ISearchableContributor;
use Override;

/**
 * Contributes a compact surrogate as fields and a heavy transcript only as embeddable text.
 */
final class StubTranscriptMediaContributor implements ISearchableContributor
{
    #[Override]
    public function contributesTo(): string
    {
        return Media::class;
    }

    #[Override]
    public function searchableFields(Model $model): array
    {
        return ['idea' => 'solitude', 'entities' => ['beacon', 'sea']];
    }

    #[Override]
    public function searchableMapping(): array
    {
        return [];
    }

    #[Override]
    public function embeddableText(Model $model): ?string
    {
        return 'whispered transcript secret';
    }
}
