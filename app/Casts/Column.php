<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

final readonly class Column
{
    public ColumnType $type;

    public function __construct(public string $name, string|ColumnType $type = ColumnType::COLUMN)
    {
        $this->type = $type instanceof ColumnType ? $type : ColumnType::tryFrom(mb_strtolower($type));
    }
}
