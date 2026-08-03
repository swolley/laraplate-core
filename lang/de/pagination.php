<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'all' => 'Alle',
    'next' => 'Nächste &raquo;',
    'previous' => '&laquo; Vorherige',
    'rowsForPage' => 'Zeilen pro Seite',
    'rowsOf' => 'Zeilen von',
    'selected' => 'ausgewählt',
    'overview' => '{1} Angezeigt 1 Ergebnis in :seconds s|[2,*] Angezeigt von :first bis :last von :total Ergebnissen in :seconds s',
];

if ($default_locale !== 'de') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/pagination.php"));
}

return $translations;
