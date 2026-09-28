<?php

declare(strict_types=1);

use Modules\Core\Media\MetadataExtractor;

/**
 * Build a raw IPTC binary tag (record, dataset, value); PHP has no native helper.
 */
function iptcTag(int $record, int $dataset, string $value): string
{
    $length = mb_strlen($value, '8bit');

    return chr(0x1C) . chr($record) . chr($dataset) . chr($length >> 8) . chr($length & 0xFF) . $value;
}

function jpegWithIptc(string $caption, array $keywords): string
{
    $image = imagecreatetruecolor(2, 2);
    $base = tempnam(sys_get_temp_dir(), 'mx') . '.jpg';
    imagejpeg($image, $base);

    $iptc = iptcTag(2, 120, $caption);

    foreach ($keywords as $keyword) {
        $iptc .= iptcTag(2, 25, $keyword);
    }

    $embedded = iptcembed($iptc, $base);
    $path = tempnam(sys_get_temp_dir(), 'mx') . '.jpg';
    file_put_contents($path, $embedded);
    @unlink($base);

    return $path;
}

it('extracts IPTC caption, keywords, dimensions and a content hash from an image', function (): void {
    $path = jpegWithIptc('A safari caption', ['lion', 'safari']);

    $meta = (new MetadataExtractor())->extract($path, 'image/jpeg');

    expect($meta->description)->toBe('A safari caption')
        ->and($meta->keywords)->toBe(['lion', 'safari'])
        ->and($meta->source)->toBe('iptc')
        ->and($meta->technical['width'] ?? null)->toBe(2)
        ->and($meta->technical['height'] ?? null)->toBe(2)
        ->and($meta->contentHash)->toMatch('/^[0-9a-f]{64}$/');

    @unlink($path);
});

it('returns only a content hash for an unsupported type', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'mx') . '.txt';
    file_put_contents($path, 'plain text, nothing to understand');

    $meta = (new MetadataExtractor())->extract($path, 'text/plain');

    expect($meta->description)->toBeNull()
        ->and($meta->keywords)->toBe([])
        ->and($meta->source)->toBe('')
        ->and($meta->contentHash)->toMatch('/^[0-9a-f]{64}$/');

    @unlink($path);
});

it('never throws on a corrupt file, degrading to the hash', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'mx') . '.jpg';
    file_put_contents($path, 'not really a jpeg');

    $meta = (new MetadataExtractor())->extract($path, 'image/jpeg');

    expect($meta->contentHash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($meta->description)->toBeNull();

    @unlink($path);
});
