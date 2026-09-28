<?php

declare(strict_types=1);

namespace Modules\Core\Media;

/**
 * Normalized result of deterministic embedded-metadata extraction from a media
 * file (M2). `source` names the branch that produced the descriptive fields
 * (`iptc`, `id3`, `pdf`, or empty when only technical data or nothing was
 * found), used by {@see MediaMetadataService} to decide what it may overwrite
 * on re-extraction without clobbering a human edit.
 */
final readonly class MediaMetadata
{
    /**
     * @param  list<string>  $keywords
     * @param  array<string, scalar>  $technical
     */
    public function __construct(
        public string $contentHash,
        public ?string $description,
        public array $keywords,
        public array $technical,
        public string $source,
    ) {}
}
