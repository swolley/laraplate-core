<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'reset' => 'Geslo je bilo spremenjeno!',
    'sent' => 'Opomnik za geslo poslano!',
    'throttled' => 'Počakajte pred ponovnim poskusom.',
    'token' => 'Ponastavitveni žeton je neveljaven.',
    'user' => 'Ne moremo najti uporabnika s tem e-poštnim naslovom.',
];

if ($default_locale !== 'sl') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/passwords.php"));
}

return $translations;
