<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\ACL;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @phpstan-type HasACLType HasACL
 *
 * @method hasMany<ACL> acl()
 * @method void addGlobalScope(string $name, callable $callback)
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
        static::addGlobalScope('acl', static function (Builder $builder): void {
            // TODO: Implement ACL scope
        });
    }
}
