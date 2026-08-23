<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Saves the column mapping for an import session: an object of target field name →
 * source column header. The controller further checks that every required field of
 * the target entity is mapped before the import can run.
 */
final class ImportMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*' => ['nullable', 'string'],
        ];
    }
}
