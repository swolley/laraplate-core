<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Contracts;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Modules\Core\Models\License;

interface IAuthenticationProvider
{
    /**
     * Verifica se questo provider può gestire la richiesta.
     */
    public function canHandle(Request $request): bool;

    /**
     * Autentica l'utente.
     *
     * @return array{
     *   success: bool,
     *   user: ?User,
     *   error: ?string,
     *   license: ?License
     * }
     */
    public function authenticate(Request $request): array;

    /**
     * Verifica se il provider è abilitato nella configurazione.
     */
    public function isEnabled(): bool;

    /**
     * Ritorna il nome del provider.
     */
    public function getProviderName(): string;
}
