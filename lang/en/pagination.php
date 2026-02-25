<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'all' => 'All',
    'next' => 'Next &raquo;',
    'previous' => '&laquo; Previous',
    'rowsForPage' => 'rows per page',
    'rowsOf' => 'rows of',
    'selected' => 'selected',
    'overview' => '{1} Showing 1 result in :seconds s|[2,*] Showing :first to :last of :total results in :seconds s',
];

if ($default_locale !== 'en') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/pagination.php"));
}

return $translations;
