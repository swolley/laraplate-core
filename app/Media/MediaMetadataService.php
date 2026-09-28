<?php

declare(strict_types=1);

namespace Modules\Core\Media;

use Modules\Core\Models\Media;

/**
 * Runs the deterministic {@see MetadataExtractor} against a media file and merges
 * the result into the media's Spatie `custom_properties` — no new `media`
 * columns (M3a). The descriptive fields (`description`, `keywords`) are written
 * only when empty or when a previous deterministic write owns them (tracked in a
 * `_provenance` map), so a human edit is never overwritten (M3c, Core side). The
 * `content_hash` (M15) and technical metadata are always refreshed.
 */
final readonly class MediaMetadataService
{
    /**
     * Sources this service is allowed to overwrite on re-extraction. A field with
     * no provenance entry is treated as human-authored and left alone.
     *
     * @var list<string>
     */
    private const array CORE_SOURCES = ['iptc', 'exif', 'id3', 'pdf', 'xmp'];

    public function __construct(private MetadataExtractor $extractor) {}

    /**
     * @param  array<string, mixed>  $custom
     * @return array<string, mixed>
     */
    public static function merge(array $custom, MediaMetadata $meta): array
    {
        $provenance = is_array($custom['_provenance'] ?? null) ? $custom['_provenance'] : [];

        if ($meta->description !== null && self::mayWrite($custom, $provenance, 'description')) {
            $custom['description'] = $meta->description;
            $provenance['description'] = $meta->source;
        }

        if ($meta->keywords !== [] && self::mayWrite($custom, $provenance, 'keywords')) {
            $custom['keywords'] = $meta->keywords;
            $provenance['keywords'] = $meta->source;
        }

        if ($meta->contentHash !== '') {
            $custom['content_hash'] = $meta->contentHash;
        }

        if ($meta->technical !== []) {
            $existing = is_array($custom['metadata'] ?? null) ? $custom['metadata'] : [];
            $custom['metadata'] = array_merge($existing, $meta->technical);
        }

        $custom['_provenance'] = $provenance;

        return $custom;
    }

    public function writeFor(Media $media): void
    {
        $path = $media->getPath();

        if (! is_file($path)) {
            return;
        }

        $meta = $this->extractor->extract($path, (string) $media->mime_type);

        $custom = [];

        foreach ($media->custom_properties as $key => $value) {
            $custom[(string) $key] = $value;
        }

        $media->custom_properties = self::merge($custom, $meta);
        $media->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $custom
     * @param  array<string, mixed>  $provenance
     */
    private static function mayWrite(array $custom, array $provenance, string $field): bool
    {
        $current = $custom[$field] ?? null;

        if ($current === null || $current === '' || $current === []) {
            return true;
        }

        $source = $provenance[$field] ?? null;

        return is_string($source) && in_array($source, self::CORE_SOURCES, true);
    }
}
