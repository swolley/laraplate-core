<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Media\MediaUrls;
use Modules\Core\Models\Media;
use Modules\Core\Models\MediaDraft;
use Override;

/**
 * Serializes a single {@see Media} row for the generic media API.
 *
 * @property Media $resource
 */
final class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $media = $this->resource;

        return [
            'id' => $media->getKey(),
            'uuid' => $media->uuid,
            'collection_name' => $media->collection_name,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'order_column' => $media->order_column,
            'custom_properties' => $media->custom_properties,
            'url' => $this->resolveUrl($media),
            'conversions' => $this->resolveConversions($media),
            'draft_token' => $this->resolveDraftToken($media),
        ];
    }

    /**
     * The owning draft's token when this asset is still staged in the pending
     * bucket, else null. Guarded by the stored morph type so bound-record media
     * never trigger an owner lookup.
     */
    private function resolveDraftToken(Media $media): ?string
    {
        if ($media->model_type !== MediaDraft::class) {
            return null;
        }

        $owner = $media->model;

        return $owner instanceof MediaDraft ? $owner->token : null;
    }

    /**
     * Resolve a media URL without letting a missing file break the whole list.
     */
    private function resolveUrl(Media $media, string $conversion = ''): ?string
    {
        return resolve(MediaUrls::class)->url($media, $conversion);
    }

    /**
     * Map of generated conversion name to its URL, from the media's own generated conversions.
     *
     * @return array<string, string>
     */
    private function resolveConversions(Media $media): array
    {
        $conversions = [];

        foreach (resolve(MediaUrls::class)->generatedConversions($media) as $name) {
            $url = $this->resolveUrl($media, $name);

            if ($url !== null) {
                $conversions[$name] = $url;
            }
        }

        return $conversions;
    }
}
