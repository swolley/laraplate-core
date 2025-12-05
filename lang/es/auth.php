<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Por favor intente nuevamente en :seconds segundos.',
];

if ($default_locale !== 'es') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/auth.php"));
}

return $translations;
