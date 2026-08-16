<?php

declare(strict_types=1);

namespace Modules\Core\Logging\Fingerprint;

use Override;

/**
 * Collapses the volatile payloads that make otherwise-identical errors unique:
 * the SQL statement inlined by query exceptions and the request/response body
 * inlined by HTTP client errors.
 */
final class CollapseVolatilePayloads implements Rule
{
    #[Override]
    public function apply(string $message): string
    {
        // Laravel query exceptions append " (SQL: <statement>)".
        $message = (string) preg_replace('/\(SQL:.*?\)/s', '(SQL: {sql})', $message);

        // HTTP client errors inline the body after "response:" / "body:".
        return (string) preg_replace('/((?:response|body):\s*).*$/is', '$1{body}', $message);
    }
}
