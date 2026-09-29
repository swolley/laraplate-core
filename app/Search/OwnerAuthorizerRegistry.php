<?php

declare(strict_types=1);

namespace Modules\Core\Search;

use Modules\Core\Search\Contracts\IOwnerAuthorizer;

/**
 * Core-owned registry of {@see IOwnerAuthorizer}s keyed by owner model class (M16),
 * twin of {@see SearchableContributorRegistry}. Populated at boot by the modules that
 * own media-bearing models; Core stays agnostic of their visibility rules.
 */
final class OwnerAuthorizerRegistry
{
    /**
     * @var array<class-string, IOwnerAuthorizer>
     */
    private array $authorizers = [];

    public function register(IOwnerAuthorizer $authorizer): void
    {
        $this->authorizers[$authorizer->ownerType()] = $authorizer;
    }

    /**
     * The authorizer for an owner class, matching a registered parent class too.
     */
    public function for(string $owner_class): ?IOwnerAuthorizer
    {
        if (array_key_exists($owner_class, $this->authorizers)) {
            return $this->authorizers[$owner_class];
        }

        foreach ($this->authorizers as $registered => $authorizer) {
            if (is_subclass_of($owner_class, $registered)) {
                return $authorizer;
            }
        }

        return null;
    }
}
