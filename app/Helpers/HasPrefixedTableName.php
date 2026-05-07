<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Str;

/**
 * This trait adds a prefix to the table name based on the module name.
 * The prefix is the module name in lowercase and underscored.
 * If the table name does not start with the module name, the module name is added to the beginning of the table name.
 * If the table name starts with the module name, the module name is not added to the beginning of the table name.
 * If the table name is 'app', the module name is not added to the beginning of the table name.
 * If the table name is 'app_', the module name is not added to the beginning of the table name.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @property string|null $table
 */
trait HasPrefixedTableName
{
    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        $module = Str::lower(class_module($this));
        $table = Str::snake(class_basename($this));

        return ($module !== 'app' && ! Str::startsWith($table, $module) ? $module . '_' : '') . $table;
    }
}
