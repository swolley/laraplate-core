<?php

declare(strict_types=1);

use Modules\Core\Actions\Fortify\PasswordValidationRules;


it('returns password validation rules via reflection', function (): void {
    $instance = new class
    {
        use PasswordValidationRules;
    };

    $method = new ReflectionMethod($instance, 'passwordRules');
    $rules = $method->invoke($instance);

    expect($rules)->toContain('required')
        ->and($rules)->toContain('string')
        ->and($rules)->toContain('confirmed');
});
