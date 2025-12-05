<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'reset' => 'Das Passwort wurde zurückgesetzt!',
    'sent' => 'Passworterinnerung wurde gesendet!',
    'throttled' => 'Bitte warten Sie, bevor Sie es erneut versuchen.',
    'token' => 'Der Passwort-Wiederherstellungsschlüssel ist ungültig oder abgelaufen.',
    'user' => 'Es konnte leider kein Nutzer mit dieser E-Mail-Adresse gefunden werden.',
];

if ($default_locale !== 'de') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/passwords.php"));
}

return $translations;
