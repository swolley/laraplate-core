<?php

declare(strict_types=1);

namespace Modules\Core\Media;

use Modules\Core\Models\Media;

/**
 * The safe projection of a {@see Media} for the API reader (spec 7.2): what a client needs to show the file, and
 * nothing of how it is stored.
 *
 * The raw `custom_properties` are never returned, because they hold the content hash, the provenance of every
 * field and AI analysis. Only the two values the media display uses are read out of them: `alt_text` (written by
 * the media metadata editor) and the pixel dimensions `metadata.width` / `metadata.height` (written by the
 * metadata extractor for images and videos). Neither the disks nor the original file name are returned.
 */
final readonly class MediaProjection
{
    public function __construct(private MediaUrls $urls) {}

    /**
     * @return array{id: int, collection_name: string, mime_type: string, size: int, width: int|null, height: int|null, alt_text: string|null, url: string|null, conversions: array<string, string|null>}
     */
    public function project(Media $media): array
    {
        $custom = $media->custom_properties;
        $metadata = is_array($custom['metadata'] ?? null) ? $custom['metadata'] : [];
        $alt_text = $custom['alt_text'] ?? null;

        $conversions = [];

        foreach ($this->urls->generatedConversions($media) as $name) {
            $conversions[$name] = $this->urls->safeUrl($media, $name);
        }

        return [
            'id' => $media->id,
            'collection_name' => (string) $media->collection_name,
            'mime_type' => (string) $media->mime_type,
            'size' => (int) $media->size,
            'width' => $this->dimension($metadata['width'] ?? null),
            'height' => $this->dimension($metadata['height'] ?? null),
            'alt_text' => is_string($alt_text) && $alt_text !== '' ? $alt_text : null,
            'url' => $this->urls->safeUrl($media),
            'conversions' => $conversions,
        ];
    }

    private function dimension(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
