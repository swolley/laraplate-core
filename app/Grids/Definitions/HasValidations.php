<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

// use Illuminate\Http\Request;

trait HasValidations
{
    private array $validation;

    /**
     * get validation.
     */
    public function getValidation(): ?array
    {
        return $this->validation ?? null;
    }

    public function validation($rule = null): static
    {
        $this->setValidation($rule);

        return $this;
    }

    /**
     * Undocumented function.
     */
    private function setValidation(array|string|null $rule): void
    {
        $this->validation = is_string($rule) ? explode('|', $rule) : $rule;
    }
}
