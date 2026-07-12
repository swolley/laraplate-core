<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class GraphEntityResolver
{
    public function moduleFor(Model|string $model): string
    {
        return Str::lower(class_module($model));
    }

    public function entityFor(Model $model): string
    {
        $table = $model->getTable();
        $module = $this->moduleFor($model);
        $prefix = $module . '_';

        if (Str::startsWith($table, $prefix)) {
            return Str::after($table, $prefix);
        }

        return $table;
    }

    public function nodeId(Model $model): string
    {
        return sprintf(
            '%s:%s:%s',
            $this->moduleFor($model),
            $this->entityFor($model),
            (string) $model->getKey(),
        );
    }
}
