<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Services;

use Illuminate\Http\Request;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;
use Modules\Core\Models\User;

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
                return $this->refusingServiceAccounts($provider->authenticate($request));
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

    /**
     * A service account authenticates with API tokens only. The providers refuse it themselves; this is the
     * single point every sign-in goes through, so a provider that forgets still cannot let one in.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function refusingServiceAccounts(array $result): array
    {
        $user = $result['user'] ?? null;

        if (($result['success'] ?? false) === true && $user instanceof User && $user->isServiceAccount()) {
            return [
                'success' => false,
                'user' => null,
                'error' => 'Service accounts cannot sign in',
                'license' => null,
            ];
        }

        return $result;
    }
}
