<?php

declare(strict_types=1);

namespace Modules\Core\Import\Contracts;

interface ImportPluginDiscoveryInterface
{
    public function label(): string;

    public function root(): ?string;

    public function autoloadPath(?string $root = null): ?string;

    /**
     * @return list<class-string>
     */
    public function discoverImplementations(?string $root = null): array;
}
