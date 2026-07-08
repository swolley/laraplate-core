<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

final class ContextualValidationException extends ValidationException
{
    /**
     * @var array<string, mixed>
     */
    protected array $log_context = [];

    public function __construct(Validator $validator, $response = null, $errorBag = 'default')
    {
        parent::__construct($validator, $response, $errorBag);

        if ($validator instanceof ContextualValidator) {
            $this->log_context = $validator->getLogContext();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->log_context;
    }
}
