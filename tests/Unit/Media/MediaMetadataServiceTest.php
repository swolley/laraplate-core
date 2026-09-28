<?php

declare(strict_types=1);

use Modules\Core\Media\MediaMetadata;
use Modules\Core\Media\MediaMetadataService;

function metadata(?string $description, array $keywords, string $source, string $hash = 'abc'): MediaMetadata
{
    return new MediaMetadata(
        contentHash: mb_str_pad($hash, 64, '0'),
        description: $description,
        keywords: $keywords,
        technical: ['width' => 4],
        source: $source,
    );
}

it('fills empty fields and records provenance', function (): void {
    $merged = MediaMetadataService::merge([], metadata('Caption', ['lion'], 'iptc'));

    expect($merged['description'])->toBe('Caption')
        ->and($merged['keywords'])->toBe(['lion'])
        ->and($merged['metadata'])->toBe(['width' => 4])
        ->and($merged['content_hash'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($merged['_provenance'])->toBe(['description' => 'iptc', 'keywords' => 'iptc']);
});

it('never overwrites a human-authored field (no provenance)', function (): void {
    $custom = ['description' => 'Written by an editor'];

    $merged = MediaMetadataService::merge($custom, metadata('IPTC caption', [], 'iptc'));

    expect($merged['description'])->toBe('Written by an editor');
});

it('overwrites a value it previously wrote itself', function (): void {
    $custom = ['description' => 'Old IPTC', '_provenance' => ['description' => 'iptc']];

    $merged = MediaMetadataService::merge($custom, metadata('New IPTC', [], 'iptc'));

    expect($merged['description'])->toBe('New IPTC');
});

it('always refreshes the content hash even when the description is preserved', function (): void {
    $custom = ['description' => 'Human'];

    $merged = MediaMetadataService::merge($custom, metadata('IPTC', [], 'iptc', 'abc'));

    expect($merged['description'])->toBe('Human')
        ->and($merged['content_hash'])->toBe(mb_str_pad('abc', 64, '0'));
});
