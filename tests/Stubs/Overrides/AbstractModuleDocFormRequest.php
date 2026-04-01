<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Overrides;

use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractModuleDocFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return ['id' => 'required'];
    }
}
