<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Opt-in companion to {@see ApplicationContentRetrievalProviderInterface}: a
 * provider declares the model whose row-level `select` permission gates its
 * source. The retrieval service builds the permission name from that model's
 * table (via {@see \Modules\Core\Support\PermissionName::forClass()}), the same
 * source of truth the permission seeder uses — so a module-prefixed table such
 * as `sao_tickets` is authorized against `default.sao_tickets.select`, not the
 * descriptor's short logical entity. Providers that do not implement this fall
 * back to the descriptor entity.
 */
interface ProvidesPermissionModel
{
    /**
     * @return class-string<Model>
     */
    public function permissionModel(): string;
}
