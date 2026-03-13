<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Foundation\Http\FormRequest;

class DummyFormRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => 'required|string'];
    }
}

