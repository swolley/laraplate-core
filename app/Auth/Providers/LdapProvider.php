<?php

namespace Modules\Core\Auth\Providers;

use Illuminate\Http\Request;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use Modules\Core\Models\User;
use Modules\Core\Models\License;
use Modules\Core\Auth\Contracts\AuthenticationProviderInterface;

class LdapProvider implements AuthenticationProviderInterface
{
    use AuthenticatesWithLdap;

    #[\Override]
    public function canHandle(Request $request): bool
    {
        return $request->has(['username', 'password']) &&
            config('auth.providers.ldap.enabled', false);
    }

    #[\Override]
    public function authenticate(Request $request): array
    {
        try {
            // Tenta l'autenticazione LDAP
            if (!$this->validateCredentials(
                $request->get('username'),
                $request->get('password')
            )) {
                return [
                    'success' => false,
                    'user' => null,
                    'error' => 'Invalid LDAP credentials',
                    'license' => null
                ];
            }

            // Trova o crea l'utente locale
            $ldapUser = $this->getLdapUser($request->get('username'));
            $user = $this->findOrCreateUser($ldapUser);

            // Verifica licenza
            if ($error = $this->checkLicense($user)) {
                return [
                    'success' => false,
                    'user' => null,
                    'error' => $error,
                    'license' => null
                ];
            }

            return [
                'success' => true,
                'user' => $user,
                'error' => null,
                'license' => $user->license
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'user' => null,
                'error' => 'LDAP authentication failed: ' . $e->getMessage(),
                'license' => null
            ];
        }
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return config('auth.providers.ldap.enabled', false);
    }

    #[\Override]
    public function getProviderName(): string
    {
        return 'ldap';
    }

    private function findOrCreateUser(LdapAuthenticatable $ldapUser): User
    {
        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['username' => $ldapUser->getUsername()],
            [
                'name' => $ldapUser->getName(),
                'email' => $ldapUser->getEmail(),
                'password' => bcrypt(str_random(16))
            ]
        );

        // Sincronizza i gruppi LDAP con i ruoli locali se necessario
        if (config('auth.providers.ldap.sync_groups', false)) {
            $this->syncLdapGroups($user, $ldapUser);
        }

        return $user;
    }

    private function checkLicense(User $user): ?string
    {
        if (!config('core.enable_user_licenses')) {
            return null;
        }

        if (!$user->license_id) {
            $available_license = License::query()
                ->doesntHave('user')
                ->first();

            if (
                !$available_license &&
                $user->roles->where('name', 'superadmin')->isEmpty()
            ) {
                return 'No free licenses available';
            }
        }

        return null;
    }

    private function syncLdapGroups(User $user, LdapAuthenticatable $ldapUser): void
    {
        $ldapGroups = $ldapUser->getGroups()->map(fn($group) => $group->getName());

        // Mappa i gruppi LDAP ai ruoli locali secondo la configurazione
        $roleMapping = config('auth.providers.ldap.group_mapping', []);
        $roles = $ldapGroups->map(fn($group) => $roleMapping[$group] ?? null)
            ->filter()
            ->unique()
            ->values();

        $user->syncRoles($roles);
    }
}
