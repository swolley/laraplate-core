<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Cron\CronExpression as CoreCronExpression;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CronExpression implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  CoreCronExpression|string  $value
     * @param  array<string, mixed>  $attributes
     */
    #[Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CoreCronExpression
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) ? new CoreCronExpression($value) : $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  CoreCronExpression|string  $value
     * @param  array<string, mixed>  $attributes
     */
    #[Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : $value->getExpression();
    }
}
