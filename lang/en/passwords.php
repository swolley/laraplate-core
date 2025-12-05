<?php

declare(strict_types=1);

$default_locale = (string) (config('app.locale'));

$translations = [
    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | has failed, such as for an invalid token or invalid new password.
    |
    */
    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => "We can't find a user with that email address.",
];

if ($default_locale !== 'en') {
    $translations = array_merge($translations, (array) require (__DIR__ . "/../{$default_locale}/passwords.php"));
}

return $translations;
