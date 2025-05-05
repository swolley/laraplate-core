<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Override;
use Illuminate\Database\Eloquent\Model;
use Cron\CronExpression as CoreCronExpression;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

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
        if (! isset($value)) {
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
        if (! isset($value)) {
            return null;
        }

        return is_string($value) ? $value : $value->getExpression();
    }
}
