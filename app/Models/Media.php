<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\SoftDeletes\SoftDeletes;
use Override;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * The shared media model owned by Core (the app-wide `media_model`). Any module
 * that uses {@see \Modules\Core\Helpers\HasMedia} stores rows here.
 *
 * @mixin \Eloquent
 * @mixin IdeHelperMedia
 */
final class Media extends BaseMedia
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;
    use HasVersions;
    use SoftDeletes;

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::Media->value;

    #[Override]
    protected $appends = [
        'expires_at',
    ];

    protected function getExpiresAtAttribute(): ?Carbon
    {
        $expirationDays = config('core.soft_deletes_expiration_days');

        return $this->trashed() && $expirationDays ? $this->{self::getDeletedAtColumn()}->addDays($expirationDays) : null;
    }
}
