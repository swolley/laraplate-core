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
    'overview' => '{1} Mostrato 1 risultato in :seconds s|[2,*] Mostrati da :first a :last di :total risultati in :seconds s',
];

if ($default_locale !== 'it') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/pagination.php"));
}

return $translations;
