<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Media;

use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A model owning media with one registered conversion, `thumb-low`, so the URL of a conversion can be built.
 */
final class MediaProjectionOwnerStub extends Model implements HasMedia
{
    use InteractsWithMedia;

    public $timestamps = false;

    protected $guarded = [];

    #[Override]
    protected $table = 'media_projection_owners';

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb-low')->width(100);
    }
}
