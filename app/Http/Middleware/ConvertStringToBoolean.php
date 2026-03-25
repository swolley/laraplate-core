<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;
use Override;

final class ConvertStringToBoolean extends TransformsRequest
{
    #[Override]
    /**
     * @param  string  $key
     */
    protected function transform($key, $value): mixed // @pest-ignore-type
    {
        if ($value === 'true' || $value === 'TRUE') {
            return true;
        }

        if ($value === 'false' || $value === 'FALSE') {
            return false;
        }

        return $value;
    }
}
