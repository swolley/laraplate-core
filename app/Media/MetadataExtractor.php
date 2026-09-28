<?php

declare(strict_types=1);

namespace Modules\Core\Media;

use getID3;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Deterministic embedded-metadata extraction (M2): IPTC/EXIF for images,
 * container/ID3 tags for audio/video (getid3), document properties for PDFs
 * (smalot/pdfparser). Cheap, dependency-light, and never throws on a malformed
 * file — every branch degrades to the content hash alone. No LLM, no network.
 */
final class MetadataExtractor
{
    public function extract(string $path, string $mimeType): MediaMetadata
    {
        $hash = is_file($path) ? (hash_file('sha256', $path) ?: '') : '';
        $type = mb_strtolower((string) strtok($mimeType, '/'));

        try {
            return match (true) {
                $type === 'image' => $this->image($path, $hash),
                $type === 'audio', $type === 'video' => $this->container($path, $hash),
                $mimeType === 'application/pdf' => $this->pdf($path, $hash),
                default => new MediaMetadata($hash, null, [], [], ''),
            };
        } catch (Throwable) {
            return new MediaMetadata($hash, null, [], [], '');
        }
    }

    private function image(string $path, string $hash): MediaMetadata
    {
        $technical = [];
        $size = @getimagesize($path, $info);

        if (is_array($size)) {
            $technical['width'] = $size[0];
            $technical['height'] = $size[1];
        }

        $description = null;
        $keywords = [];

        if (is_array($info) && isset($info['APP13']) && is_string($info['APP13'])) {
            $iptc = @iptcparse($info['APP13']);

            if (is_array($iptc)) {
                $description = $this->firstString($iptc['2#120'] ?? null);
                $keywords = $this->stringList($iptc['2#025'] ?? null);
            }
        }

        // EXIF is only defined for JPEG/TIFF; calling it on other or corrupt
        // files warns. Gate on the detected image type.
        $exifTypes = [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM];

        if (is_array($size) && in_array($size[2], $exifTypes, true)) {
            $exif = @exif_read_data($path);

            if (is_array($exif)) {
                foreach (['Make', 'Model', 'DateTimeOriginal'] as $key) {
                    if (isset($exif[$key]) && is_scalar($exif[$key])) {
                        $technical[mb_strtolower($key)] = $exif[$key];
                    }
                }
            }
        }

        $source = ($description !== null || $keywords !== []) ? 'iptc' : '';

        return new MediaMetadata($hash, $description, $keywords, $technical, $source);
    }

    private function container(string $path, string $hash): MediaMetadata
    {
        $info = (new getID3())->analyze($path);

        $technical = [];

        if (isset($info['playtime_seconds']) && is_numeric($info['playtime_seconds'])) {
            $technical['duration_seconds'] = (float) $info['playtime_seconds'];
        }

        if (isset($info['video']['resolution_x'], $info['video']['resolution_y'])
            && is_numeric($info['video']['resolution_x']) && is_numeric($info['video']['resolution_y'])) {
            $technical['width'] = (int) $info['video']['resolution_x'];
            $technical['height'] = (int) $info['video']['resolution_y'];
        }

        $tags = is_array($info['tags'] ?? null) ? $info['tags'] : [];
        $comments = [];

        foreach ($tags as $set) {
            if (is_array($set)) {
                $comments = array_merge($comments, $set);
            }
        }

        $description = $this->firstString($comments['title'] ?? ($comments['comment'] ?? null));
        $keywords = $this->stringList($comments['genre'] ?? null);
        $source = ($description !== null || $keywords !== []) ? 'id3' : '';

        return new MediaMetadata($hash, $description, $keywords, $technical, $source);
    }

    private function pdf(string $path, string $hash): MediaMetadata
    {
        $pdf = (new PdfParser())->parseFile($path);
        $details = $pdf->getDetails();

        $description = $this->firstString($details['Title'] ?? ($details['Subject'] ?? null));
        $keywords = $this->stringList($details['Keywords'] ?? null);

        $technical = [];

        if (isset($details['Pages']) && is_numeric($details['Pages'])) {
            $technical['pages'] = (int) $details['Pages'];
        }

        $source = ($description !== null || $keywords !== []) ? 'pdf' : '';

        return new MediaMetadata($hash, $description, $keywords, $technical, $source);
    }

    private function firstString(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $trimmed = mb_trim($item);

                if ($trimmed !== '') {
                    $out[] = $trimmed;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
