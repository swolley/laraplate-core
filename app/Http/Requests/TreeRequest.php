<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\TreeRequestData;
use Override;

/**
 * @property bool $parents
 * @property bool $children
 */
final class TreeRequest extends DetailRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @psalm-suppress LessSpecificImplementedReturnType
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function rules(): array
    {
        $pk_keys = is_array($this->primaryKey) ? $this->primaryKey : [$this->primaryKey];
        $rules = array_diff_key(parent::rules(), array_flip($pk_keys));

        return $rules + [
            'parents' => 'boolean',
            'children' => 'boolean',
        ];
    }

    /**
     * Return data specific to tree request.
     */
    #[Override]
    public function parsed(): TreeRequestData
    {
        return new TreeRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
