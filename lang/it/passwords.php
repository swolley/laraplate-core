<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'reset' => 'La password è stata reimpostata!',
    'sent' => 'Ti abbiamo inviato una email con il link per il reset della password!',
    'throttled' => 'Per favore, attendi prima di riprovare.',
    'token' => 'Questo token di reset della password non è valido.',
    'user' => 'Non riusciamo a trovare un utente con questo indirizzo email.',
];

if ($default_locale !== 'it') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/passwords.php"));
}

return $translations;
