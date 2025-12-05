<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    'reset' => 'Su contraseña ha sido restablecida.',
    'sent' => 'Le hemos enviado por correo electrónico el enlace para restablecer su contraseña.',
    'throttled' => 'Por favor espere antes de intentar de nuevo.',
    'token' => 'El token de restablecimiento de contraseña es inválido.',
    'user' => 'No encontramos ningún usuario con ese correo electrónico.',
];

if ($default_locale !== 'es') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/passwords.php"));
}

return $translations;
