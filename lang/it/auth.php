<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'failed' => 'Credenziali non valide.',
    'password' => 'Password non corretta.',
    'throttle' => 'Troppi tentativi di accesso. Riprova tra :seconds secondi.',
    'module_scope_denied' => 'Non hai accesso a questo modulo.',
];

if ($default_locale !== 'it') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/auth.php"));
}

return $translations;
