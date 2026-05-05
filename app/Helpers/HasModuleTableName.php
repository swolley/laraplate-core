<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Str;

trait HasModuleTableName
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
