<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Support\EntityImporterRegistry;

/**
 * Starts a bulk import: an uploaded file and the target entity to import it into.
 * The entity must be one registered in the {@see EntityImporterRegistry}, and the
 * file extension must be a supported source format.
 */
final class ImportUploadRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:' . implode(',', ImportSourceFormat::values()) . ',txt', 'max:51200'],
            'entity_key' => ['required', 'string', Rule::in(array_keys(app(EntityImporterRegistry::class)->all()))],
            'options' => ['sometimes', 'array'],
            'options.delimiter' => ['sometimes', 'string', 'size:1'],
        ];
    }
}
