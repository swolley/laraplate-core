<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Single source of truth for permission names.
 *
 * The `{connection}.{table}.{operation}` convention was previously rebuilt by
 * hand in the authorization service, the ERP policy and the ERP seeder. Three
 * copies of one convention is three chances to drift.
 */
final class PermissionName
{
    public static function build(string $connection, string $table, string $operation): string
    {
        return sprintf('%s.%s.%s', $connection, $table, $operation);
    }

    public static function forModel(Model $model, string $operation): string
    {
        return self::build(
            $model->getConnectionName() ?? 'default',
            $model->getTable(),
            $operation,
        );
    }

    /**
     * Builds the name without booting the model, for callers that only hold a
     * class string such as seeders enumerating their domain permissions.
     *
     * @param  class-string<Model>  $model_class
     */
    public static function forClass(string $model_class, string $operation): string
    {
        /** @var Model $instance */
        $instance = new ReflectionClass($model_class)->newInstanceWithoutConstructor();

        return self::forModel($instance, $operation);
    }
}
