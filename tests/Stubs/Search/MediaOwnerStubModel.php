<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Search\Traits\Searchable;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A searchable model owning media, standing in for an article or a ticket.
 */
final class MediaOwnerStubModel extends Model implements HasMedia, ISearchableModel
{
    use InteractsWithMedia;
    use Searchable;

    public $timestamps = false;

    protected $guarded = [];

    protected bool $softDeletesEnabled = false;

    #[Override]
    protected $table = 'media_owner_stubs';
}
