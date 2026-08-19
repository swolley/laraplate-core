<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Modules\Core\Models\ACL;
use Modules\Core\Services\AclResolverService;

/**
 * Keeps the resolver cache consistent with ACL writes.
 *
 * Effective ACLs are cached per user/permission for {@see AclResolverService::CACHE_TTL}.
 * Without invalidation an ACL change (including toggling `unrestricted` to override an
 * inherited role ACL) would not take effect until the entry expired, so every create,
 * update, delete and restore flushes the cached ACL resolutions.
 */
final class AclObserver
{
    public function __construct(private readonly AclResolverService $resolver) {}

    public function saved(ACL $acl): void
    {
        $this->resolver->flushCache();
    }

    public function deleted(ACL $acl): void
    {
        $this->resolver->flushCache();
    }

    public function restored(ACL $acl): void
    {
        $this->resolver->flushCache();
    }
}
