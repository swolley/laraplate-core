<?php

declare(strict_types=1);

namespace Modules\Core\Media;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Media;
use Throwable;

/**
 * The one place that turns a {@see Media} and one of its generated conversions into a URL.
 *
 * {@see self::url()} is the plain public URL the media API has always returned. {@see self::safeUrl()} is the one
 * a response for the anonymous or API reader carries: a disk configured as public gives its plain URL, a disk
 * whose driver can sign gives a temporary URL valid for `core.media.signed_url_ttl_minutes`, and any other disk
 * (a local one that is not public, an unknown one) gives no URL at all.
 */
final class MediaUrls
{
    /**
     * The plain URL of the original, or of a conversion; null when it cannot be built, so a missing file does not
     * break the whole list.
     */
    public function url(Media $media, string $conversion = ''): ?string
    {
        try {
            return $media->getUrl($conversion);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The URL of the original, or of a conversion, that may be handed to a reader who has no session.
     */
    public function safeUrl(Media $media, string $conversion = ''): ?string
    {
        $disk = $conversion === '' ? $media->disk : ($media->conversions_disk ?: $media->disk);

        try {
            if (config("filesystems.disks.{$disk}.visibility") === 'public') {
                return $media->getUrl($conversion);
            }

            if (! Storage::disk($disk)->providesTemporaryUrls()) {
                return null;
            }

            return $media->getTemporaryUrl(now()->addMinutes(config()->integer('core.media.signed_url_ttl_minutes', 60)), $conversion);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The names of the conversions the media has generated, whatever its collection: image `thumb-*`, video
     * `video_thumb-*`, and so on, with no conversion name hardcoded.
     *
     * @return list<string>
     */
    public function generatedConversions(Media $media): array
    {
        $names = [];

        foreach ($media->getGeneratedConversions() as $name => $generated) {
            $conversion_name = (string) $name;

            if ($generated === true && $media->hasGeneratedConversion($conversion_name)) {
                $names[] = $conversion_name;
            }
        }

        return $names;
    }
}
