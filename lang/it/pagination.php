<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'all' => 'Tutte',
    'next' => 'Prossimo &raquo;',
    'previous' => '&laquo; Precedente',
    'rowsForPage' => 'righe per pagina',
    'rowsOf' => 'righe di',
    'selected' => 'selezionate',
];

if ($default_locale !== 'it') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/pagination.php"));
}

return $translations;
