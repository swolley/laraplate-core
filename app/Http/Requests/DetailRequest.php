<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\DetailRequestData;
use Override;

class DetailRequest extends SelectRequest
{
    #[Override]
    public function parsed(): DetailRequestData
    {
        /** @phpstan-ignore method.notFound */
        return new DetailRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $to_merge = [
            'filters' => [],
        ];

        foreach (is_array($this->primaryKey) ? $this->primaryKey : [$this->primaryKey] as $key) {
            $to_merge[$key] = ['required'];
            $to_merge['filters'][] = ['property' => $key, 'value' => $this->{$key}];
        }

        /** @phpstan-ignore method.notFound */
        $this->merge($to_merge);
    }
}
