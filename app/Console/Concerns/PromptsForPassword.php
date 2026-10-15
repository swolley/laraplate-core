<?php

declare(strict_types=1);

namespace Modules\Core\Console\Concerns;

use function Laravel\Prompts\password;

use Illuminate\Support\Str;

trait PromptsForPassword
{
    use HasCommandUtils;

    /**
     * Ask for a password and its confirmation, or generate a random one when left blank.
     *
     * @param  array<string, mixed>  $validations  rules keyed by attribute; only `password` is applied
     * @return array{password: string, generated: bool}
     */
    private function promptPassword(array $validations, string $label = 'Password'): array
    {
        $password = password($label, 'Type a password or let blank to randomly generate it', false, fn (string $value): ?string => $value === '' ? null : $this->validationCallback('password', $value, $validations));

        if ($password === '') {
            return ['password' => Str::password(), 'generated' => true];
        }

        password('Confirm password', required: true, validate: fn (string $value): ?string => $this->validationCallback('password', $value, ['password' => 'in:' . $password]));

        return ['password' => $password, 'generated' => false];
    }
}
