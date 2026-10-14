<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Http\Request;

/**
 * A request whose validated input is all its input, for SearchRequestData built by hand in a test.
 */
final class ValidatedSearchRequestStub extends Request
{
    /**
     * @param  array-key|null  $key
     */
    public function validated(mixed $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->all() : ($this->all()[$key] ?? $default);
    }
}
