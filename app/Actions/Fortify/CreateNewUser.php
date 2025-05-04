<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Fortify;

use Override;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * {@inheritdoc}
     */
    #[Override]
    public function create(array $input)
    {
        $user_class = user_class();
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique($user_class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return $user_class::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
