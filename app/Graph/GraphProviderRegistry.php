<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Support\Str;
use Modules\Core\Graph\Contracts\GraphProviderInterface;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Override;

final class GraphProviderRegistry implements GraphProviderRegistryInterface
{
    /**
     * @var array<string, GraphProviderInterface>
     */
    private array $providers = [];

    #[Override]
    public function register(GraphProviderInterface $provider, string $module, ?string $entity = null): void
    {
        $this->providers[$this->key($module, $entity)] = $provider;
    }

    #[Override]
    public function providerFor(string $module, string $entity): ?GraphProviderInterface
    {
        return $this->providers[$this->key($module, $entity)]
            ?? $this->providers[$this->key($module, null)]
            ?? null;
    }

    private function key(string $module, ?string $entity): string
    {
        $module = Str::lower($module);
        $entity = $entity === null ? '*' : Str::lower($entity);

        return $module . ':' . $entity;
    }
}
