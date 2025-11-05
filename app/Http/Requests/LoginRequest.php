<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @inheritdoc
 * @package Modules\Core\Http\Requests
 * @property ?string $username
 * @property ?string $email
 * @property string $password
 * @property ?bool $rememberme
 */
final class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required_without:email'],
            'email' => ['required_without:username', 'email'],
            'password' => ['required'],
            'rememberme' => ['boolean', 'nullable'],
        ];
    }
}
