<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'failed' => 'Ti podatki se ne ujemajo z našimi.',
    'password' => 'Greslo ni pravilno.',
    'throttle' => 'Preveč poskusov prijave. Prosimo, poskusite ponovno čez :seconds sekund.',
];

if ($default_locale !== 'sl') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/auth.php"));
}

return $translations;
