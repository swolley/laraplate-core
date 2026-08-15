<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;

/**
 * The app-wide media file namer. Names `thumb-*` conversions compactly and falls
 * back to spatie's default for everything else. Owned by Core so the global
 * `file_namer` config can reference a foundation class.
 */
final class MediaFileNamer extends DefaultFileNamer
{
    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        if (Str::contains($conversion->getName(), 'thumb-')) {
            return sprintf(
                '%s-%s',
                pathinfo($fileName, PATHINFO_FILENAME),
                Str::after($conversion->getName(), '-'),
            );
        }

        return parent::conversionFileName($fileName, $conversion);
    }
}
