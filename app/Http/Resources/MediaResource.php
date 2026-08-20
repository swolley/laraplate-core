<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\Media;
use Override;
use Throwable;

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
        ];
    }

    /**
     * Resolve a media URL without letting a missing file break the whole list.
     */
    private function resolveUrl(Media $media, string $conversion = ''): ?string
    {
        try {
            return $media->getUrl($conversion);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Map of generated conversion name to its URL. Derived from the media's own
     * generated conversions so it stays correct for any collection (image
     * `thumb-*`, video `video_thumb-*`, …) without hardcoding conversion names.
     *
     * @return array<string, string>
     */
    private function resolveConversions(Media $media): array
    {
        $conversions = [];

        foreach ($media->getGeneratedConversions() as $name => $generated) {
            $conversion_name = (string) $name;

            if ($generated !== true || ! $media->hasGeneratedConversion($conversion_name)) {
                continue;
            }

            $url = $this->resolveUrl($media, $conversion_name);

            if ($url !== null) {
                $conversions[$conversion_name] = $url;
            }
        }

        return $conversions;
    }
}
