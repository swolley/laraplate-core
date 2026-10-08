<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Services;

use Illuminate\Http\Request;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;

final readonly class AuthenticationService
{
    /**
     * @param  array<int,IAuthenticationProvider>  $providers
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

    /**
     * Whether the enabled provider that handles the request already carries a second factor.
     */
    public function satisfiesSecondFactor(Request $request): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled() && $provider->canHandle($request)) {
                return $provider->satisfiesSecondFactor();
            }
        }

        return false;
    }

    public function getAvailableProviders(): array
    {
        return array_map(
            static fn (IAuthenticationProvider $provider): string => $provider->getProviderName(),
            array_filter($this->providers, static fn (IAuthenticationProvider $p): bool => $p->isEnabled()),
        );
    }
}
