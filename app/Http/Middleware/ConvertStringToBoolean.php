<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Override;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

final class ConvertStringToBoolean extends TransformsRequest
{
    #[Override]
    protected function transform($key, $value)
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
