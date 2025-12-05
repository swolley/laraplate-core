<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'all' => 'Todas',
    'next' => 'Siguiente &raquo;',
    'previous' => '&laquo; Anterior',
    'rowsForPage' => 'filas por página',
    'rowsOf' => 'filas de',
    'selected' => 'seleccionadas',
];

if ($default_locale !== 'es') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/pagination.php"));
}

return $translations;
