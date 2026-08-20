<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service update of the authenticated user's own UI preferences. No target
 * id is accepted — the endpoint always writes the current session user's row.
 *
 * @property array<string, mixed> $preferences
 */
final class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
        ];
    }
}
