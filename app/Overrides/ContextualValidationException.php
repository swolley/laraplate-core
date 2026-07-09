<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Exception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Helpers\ValidationExceptionDescriber;

final class ContextualValidationException extends ValidationException
{
    /**
     * @var array<string, mixed>
     */
    protected array $log_context = [];

    public function __construct(Validator $validator, $response = null, $errorBag = 'default')
    {
        $this->validator = $validator;
        $this->response = $response;
        $this->errorBag = $errorBag;

        if ($validator instanceof ContextualValidator) {
            $this->log_context = $validator->getLogContext();
        }

        Exception::__construct(ValidationExceptionDescriber::describe($this));
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->log_context;
    }
}
