<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'all' => 'Vse',
    'next' => 'Naslednji &raquo;',
    'previous' => '&laquo; Prejšnji',
    'rowsForPage' => 'vrstic na stran',
    'rowsOf' => 'vrstic od',
    'selected' => 'izbranih',
];

if ($default_locale !== 'sl') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/pagination.php"));
}

return $translations;
