<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Services;

use Illuminate\Http\Request;
use Modules\Core\Auth\Contracts\AuthenticationProviderInterface;

final readonly class AuthenticationService
{
    /**
     * @param  array<int,AuthenticationProviderInterface>  $providers
     */
    public function __construct(
        private array $providers,
    ) {}

    public function authenticate(Request $request): array
    {
        foreach ($this->providers as $provider) {
            if (! $provider->isEnabled()) {
                continue;
            }

            if ($provider->canHandle($request)) {
                return $provider->authenticate($request);
            }
        }

        return [
            'success' => false,
            'user' => null,
            'error' => 'No suitable authentication provider found',
            'license' => null,
        ];
    }

    public function getAvailableProviders(): array
    {
        return array_map(
            fn (AuthenticationProviderInterface $provider): string => $provider->getProviderName(),
            array_filter($this->providers, fn ($p): bool => $p->isEnabled()),
        );
    }
}
