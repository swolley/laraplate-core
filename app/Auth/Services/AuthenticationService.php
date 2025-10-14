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

    public function getAvailableProviders(): array
    {
        return array_map(
            fn (IAuthenticationProvider $provider): string => $provider->getProviderName(),
            array_filter($this->providers, fn (IAuthenticationProvider $p): bool => $p->isEnabled()),
        );
    }
}
