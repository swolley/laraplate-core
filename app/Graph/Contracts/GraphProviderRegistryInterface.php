<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Contracts;

interface GraphProviderRegistryInterface
{
    public function register(GraphProviderInterface $provider, string $module, ?string $entity = null): void;

    public function providerFor(string $module, string $entity): ?GraphProviderInterface;
}
