<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent\Exceptions;

use RuntimeException;
final class ApplicationContentUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Application content is unavailable.');
    }
}
