<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\ACL;

/**
 * @phpstan-type HasACLType HasACL
 *
 * @template TModel of Model
 */
trait HasACL
{
    /**
     * @return HasMany<ACL>
     */
    public function acl(): HasMany
    {
        return $this->hasMany(ACL::class);
    }

    protected static function bootHasACL(): void
    {
        static::addGlobalScope('acl', function (Builder $builder): void {});
    }
}
