<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Traits;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Auth\Services\AclService;

trait HasAcl
{
    public function scopeWithAcl(Builder $query, int $permission_id): Builder
    {
        return app(AclService::class)->applyAclToQuery($query, $permission_id);
    }

    public function getAclFields(): array
    {
        return $this->fillable;
    }
}
