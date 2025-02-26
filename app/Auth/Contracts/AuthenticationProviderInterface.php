<?php

namespace Modules\Core\Auth\Contracts;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User;
use Modules\Core\Models\License;

interface AuthenticationProviderInterface
{
    /**
     * Verifica se questo provider può gestire la richiesta
     */
    public function canHandle(Request $request): bool;

    /**
     * Autentica l'utente
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
     * Verifica se il provider è abilitato nella configurazione
     */
    public function isEnabled(): bool;

    /**
     * Ritorna il nome del provider
     */
    public function getProviderName(): string;
}
