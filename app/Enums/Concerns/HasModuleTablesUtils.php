<?php

declare(strict_types=1);

namespace Modules\Core\Enums\Concerns;

use Illuminate\Support\Str;

trait HasModuleTablesUtils
{
    public static function getModelClassName(self $case): string
    {
        $current_module = class_module($case);
        $prefix = '';
        
        if (Str::endsWith($case->name, 'Translation')) {
            $prefix = 'Translations\\';
        } else if (Str::startsWith($case->name, 'Has') || Str::endsWith($case->name, 'les')) {
            $prefix = 'Pivot\\';
        }

        $class_name = "Modules\\{$current_module}\\Models\\{$prefix}" . Str::singular($case->name);
                
        if (!class_exists($class_name)) {
            throw new \Exception("Not a $current_module module table: {$case->value}, model: {$class_name}");
        }

        return $class_name;
    }
}