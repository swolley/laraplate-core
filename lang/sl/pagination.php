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
    'overview' => '{1} Prikazano 1 rezultat v :seconds s|[2,*] Prikazano od :first do :last od :total rezultatov v :seconds s',
];

if ($default_locale !== 'sl') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/pagination.php"));
}

return $translations;
