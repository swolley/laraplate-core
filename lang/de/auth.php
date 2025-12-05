<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'failed' => 'Diese Kombination aus Zugangsdaten wurde nicht in unserer Datenbank gefunden.',
    'password' => 'Das Passwort ist falsch.',
    'throttle' => 'Zu viele Login-Versuche. Versuchen Sie es bitte in :seconds Sekunden nochmal.',
];

if ($default_locale !== 'de') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/auth.php"));
}

return $translations;
