<?php

declare(strict_types=1);

namespace Modules\Core\Import\Exceptions;

use RuntimeException;

/**
 * Raised by an entity importer when one row cannot be imported — a validation
 * failure, a conflict, a missing required value. The processing job catches it,
 * records the row and its messages in the failure report, and moves on, so one bad
 * row never aborts the import.
 */
final class RowImportException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors  Field name → messages ('_' for row-level).
     */
    public function __construct(private readonly array $errors, string $message = 'Row import failed')
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function withErrors(array $errors): self
    {
        $first = '';

        foreach ($errors as $messages) {
            if ($messages !== []) {
                $first = (string) $messages[0];

                break;
            }
        }

        return new self($errors, $first === '' ? 'Row import failed' : $first);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
