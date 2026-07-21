<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent\Contracts;

use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;

interface ApplicationContentRetrievalProviderRegistryInterface
{
    public function register(ApplicationContentRetrievalProviderInterface $provider): void;

    public function providerFor(string $source): ?ApplicationContentRetrievalProviderInterface;

    /**
     * @return list<ApplicationContentSourceDescriptor>
     */
    public function descriptors(): array;
}
