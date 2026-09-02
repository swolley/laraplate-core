<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use function config;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Single source of truth for permission names.
 *
 * The `{connection}.{table}.{operation}` convention was previously rebuilt by
 * hand in the authorization service, the ERP policy and the ERP seeder. Three
 * copies of one convention is three chances to drift.
 *
 * The connection segment is a *logical* name: a model that does not pin a
 * connection is named `default`, never the driver `database.default` happens to
 * resolve to. Baking the resolved name in would make the very same permission
 * unmatchable across environments, and it would also split one permission in
 * two within a single environment, since Eloquent stamps the resolved connection
 * onto every hydrated record (a fresh instance reports `null`, the record loaded
 * back from the database reports `mysql`).
 */
final class PermissionName
{
    public static function build(string $connection, string $table, string $operation): string
    {
        return sprintf('%s.%s.%s', self::normalizeConnection($connection), $table, $operation);
    }

    public static function forModel(Model $model, string $operation): string
    {
        return self::build(
            self::normalizeConnection($model->getConnectionName()),
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

    /**
     * Collapses "no connection" and the resolved default connection onto `default`;
     * a secondary connection keeps its own name, since it identifies a distinct
     * permission.
     */
    public static function normalizeConnection(?string $connection): string
    {
        if ($connection === null || $connection === '') {
            return 'default';
        }

        return $connection === (string) config('database.default') ? 'default' : $connection;
    }
}
