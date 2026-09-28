<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\IEmbeddableModel;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Contracts\ISoftDeletableModel;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\Observers\MediaMetadataObserver;
use Modules\Core\Search\SearchableContributorRegistry;
use Modules\Core\Search\Traits\Searchable;
use Modules\Core\SoftDeletes\SoftDeletes;
use Override;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * The shared media model owned by Core (the app-wide `media_model`). Any module
 * that uses {@see \Modules\Core\Helpers\HasMedia} stores rows here.
 *
 * Searchable (M5): a claimed media indexes standalone. Its display metadata lives
 * in Spatie `custom_properties` (M3a, no new columns); AI-derived text joins the
 * embeddable content and the document through the Core searchable-contributor
 * seam (M4a), so Core never references the AI module.
 *
 * @phpstan-use Searchable<Media>
 */
#[ObservedBy(MediaMetadataObserver::class)]
final class Media extends BaseMedia implements IEmbeddableModel, ISearchableModel, ISoftDeletableModel
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;
    use HasVersions;
    use Searchable {
        Searchable::toSearchableArray as private toSearchableArrayTrait;
    }
    use SoftDeletes;

    /**
     * The single embeddable attribute is a computed accessor composing the Core
     * display surrogate (from `custom_properties`) with contributed AI text.
     *
     * @var list<string>
     */
    protected array $embed = ['searchable_embed_text'];

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::Media->value;

    #[Override]
    protected $appends = [
        'expires_at',
    ];

    /**
     * A media is indexed only once claimed onto a real owner (M14): while it is
     * staged, its owner is a {@see MediaDraft} and it must not enter search.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->model_type !== (new MediaDraft())->getMorphClass();
    }

    /**
     * The vector source (M5): the Core display surrogate (description + keywords
     * from `custom_properties`) plus any AI-contributed text (transcript, idea,
     * intent) added through the contributor seam. Read by the `Searchable` trait
     * via the `$embed` list.
     */
    public function getSearchableEmbedTextAttribute(): string
    {
        $custom = $this->custom_properties;

        $parts = [];

        $description = $custom['description'] ?? null;

        if (is_string($description) && $description !== '') {
            $parts[] = $description;
        }

        $keywords = $custom['keywords'] ?? [];

        if (is_array($keywords)) {
            foreach ($keywords as $keyword) {
                if (is_string($keyword) && $keyword !== '') {
                    $parts[] = $keyword;
                }
            }
        }

        $contributed = app(SearchableContributorRegistry::class)->embeddableTextFor($this);

        if ($contributed !== '') {
            $parts[] = $contributed;
        }

        return mb_trim(implode(' ', $parts));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $document = $this->toSearchableArrayTrait();

        $custom = $this->custom_properties;

        // Facets shipped from day one (M9) so a large index stays filterable and
        // Phase 2 needs no reindex to add the visual `track`.
        $document['mime'] = (string) $this->mime_type;
        $document['track'] = 'media';
        $document['collection'] = (string) $this->collection_name;

        $keywords = $custom['keywords'] ?? [];
        $document['keywords'] = is_array($keywords)
            ? array_values(array_filter($keywords, static fn (mixed $k): bool => is_string($k) && $k !== ''))
            : [];

        $description = $custom['description'] ?? null;
        $document['description'] = is_string($description) ? $description : '';

        return $document;
    }

    protected function getExpiresAtAttribute(): ?Carbon
    {
        $expirationDays = config('core.soft_deletes.expiration_days');

        return $this->trashed() && $expirationDays ? $this->{self::getDeletedAtColumn()}->addDays($expirationDays) : null;
    }
}
