<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'locale' => 'sl_SI',
    'language' => 'Slovenščina',
];

if ($default_locale !== 'sl') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/app.php"));
}

return $translations;
