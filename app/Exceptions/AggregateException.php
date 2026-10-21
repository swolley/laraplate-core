<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Modules\Core\Enums\AggregateErrorCode;
use RuntimeException;

/**
 * An aggregate request the engine refuses: it carries the contract code, a message for the client and, when the
 * refusal is about one part of the request, the path of that part. The message never holds SQL text.
 */
final class AggregateException extends RuntimeException
{
    public function __construct(
        public readonly AggregateErrorCode $errorCode,
        string $message,
        public readonly ?string $field = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array{code: string, message: string, field?: string}
     */
    public function toArray(): array
    {
        $error = [
            'code' => $this->errorCode->value,
            'message' => $this->getMessage(),
        ];

        if ($this->field !== null) {
            $error['field'] = $this->field;
        }

        return $error;
    }
}
