<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\Models\Media;
use Modules\Core\SoftDeletes\SoftDeletes;

it('maps the shared vend_media table', function (): void {
    expect((new Media)->getTable())->toBe(CoreTables::Media->value)
        ->and(CoreTables::Media->value)->toBe('vend_media');
});

it('carries the foundation media traits', function (): void {
    $traits = class_uses_recursive(Media::class);

    expect($traits)->toContain(HasFactory::class)
        ->toContain(HasVersions::class)
        ->toContain(SoftDeletes::class);
});

it('appends a soft-delete-aware expires_at', function (): void {
    $media = new Media;

    expect($media->toArray())->toHaveKey('expires_at')
        // A non-trashed media has no expiry.
        ->and($media->expires_at)->toBeNull();
});

it('is bound as the app-wide media model', function (): void {
    expect(config('media-library.media_model'))->toBe(Media::class);
});
