<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent;

use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderInterface;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderRegistryInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;
use Modules\Core\ApplicationContent\Exceptions\DuplicateApplicationContentSourceException;

final class ApplicationContentRetrievalProviderRegistry implements ApplicationContentRetrievalProviderRegistryInterface
{
    /**
     * @var array<string, ApplicationContentRetrievalProviderInterface>
     */
    private array $providers = [];

    /**
     * @var array<string, ApplicationContentSourceDescriptor>
     */
    private array $source_descriptors = [];

    public function register(ApplicationContentRetrievalProviderInterface $provider): void
    {
        $descriptor = $provider->descriptor();
        $source = $descriptor->source;

        if (isset($this->providers[$source])) {
            throw new DuplicateApplicationContentSourceException(
                'An application content provider is already registered for this source.',
            );
        }

        $this->providers[$source] = $provider;
        $this->source_descriptors[$source] = $descriptor;
    }

    public function providerFor(string $source): ?ApplicationContentRetrievalProviderInterface
    {
        try {
            $source = ApplicationContentSourceDescriptor::normalizeSource($source);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->providers[$source] ?? null;
    }

    public function descriptors(): array
    {
        $descriptors = $this->source_descriptors;
        ksort($descriptors, SORT_STRING);

        return array_values($descriptors);
    }
}
